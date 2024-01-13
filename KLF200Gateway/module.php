<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/KLF200Class.php';  // diverse Klassen
eval('declare(strict_types=1);namespace KLF200Gateway {?>' . file_get_contents(__DIR__ . '/../libs/helper/BufferHelper.php') . '}');
eval('declare(strict_types=1);namespace KLF200Gateway {?>' . file_get_contents(__DIR__ . '/../libs/helper/SemaphoreHelper.php') . '}');
eval('declare(strict_types=1);namespace KLF200Gateway {?>' . file_get_contents(__DIR__ . '/../libs/helper/DebugHelper.php') . '}');
eval('declare(strict_types=1);namespace KLF200Gateway {?>' . file_get_contents(__DIR__ . '/../libs/helper/ParentIOHelper.php') . '}');
eval('declare(strict_types=1);namespace KLF200Gateway {?>' . file_get_contents(__DIR__ . '/../libs/helper/VariableHelper.php') . '}');

/**
 * KLF200Gateway Klasse implementiert die KLF 200 API
 * Erweitert IPSModule.
 *
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2024 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 *
 * @version       1.00
 *
 * @method bool lock(string $ident)
 * @method void unlock(string $ident)
 * @method void SetValueInteger(string $Ident, int $value)
 * @method void SetValueString(string $Ident, string $value)
 *
 * @property int $ParentID
 * @property string $Host
 * @property string $ReceiveBuffer
 * @property \KLF200\APIData $ReceiveAPIData
 * @property \KLF200\APIData[] $ReplyAPIData
 * @property array $Nodes
 * @property int $WaitForNodes
 * @property bool $GetNodeInfoIsRunning
 */
class KLF200Gateway extends IPSModule
{
    use \KLF200Gateway\Semaphore,
        \KLF200Gateway\BufferHelper,
        \KLF200Gateway\DebugHelper,
        \KLF200Gateway\VariableHelper,
        \KLF200Gateway\InstanceStatus {
            \KLF200Gateway\InstanceStatus::MessageSink as IOMessageSink;
            \KLF200Gateway\InstanceStatus::RegisterParent as IORegisterParent;
            \KLF200Gateway\InstanceStatus::RequestAction as IORequestAction;
            \KLF200Gateway\DebugHelper::SendDebug as SendDebugTrait;
        }

    /**
     * Interne Funktion des SDK.
     */
    public function Create()
    {
        parent::Create();
        $this->RequireParent(\KLF200\GUID::ClientSocket);
        $this->RegisterPropertyString(\KLF200\Gateway\Property::Password, '');
        $this->RegisterPropertyBoolean(\KLF200\Gateway\Property::RebootOnShutdown, false);
        $this->RegisterAttributeBoolean(\KLF200\Gateway\Attribute::ClientSocketStateOnShutdown, false);
        $this->RegisterTimer(\KLF200\Gateway\Timer::KeepAlive, 0, 'KLF200_ReadGatewayState($_IPS[\'TARGET\']);');
        $this->Host = '';
        $this->ParentID = 0;

        $this->ReceiveBuffer = '';
        $this->ReplyAPIData = [];
        $this->GetNodeInfoIsRunning = false;
        $this->Nodes = [];

        if (IPS_GetKernelRunlevel() != KR_READY) {
            $this->RegisterMessage(0, IPS_KERNELSTARTED);
        }
        $this->RegisterMessage(0, IPS_KERNELSHUTDOWN);
    }

    /**
     * Interne Funktion des SDK.
     */
    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        $this->IOMessageSink($TimeStamp, $SenderID, $Message, $Data);
        switch ($Message) {
            case IPS_KERNELSTARTED:
                $this->UnregisterMessage(0, IPS_KERNELSTARTED);
                $this->KernelReady();
                break;
            case IPS_KERNELSHUTDOWN:
                $this->KernelShutdown();
                break;
        }
    }

    public function RequestAction($Ident, $Value)
    {
        if ($this->IORequestAction($Ident, $Value)) {
            return true;
        }
        if ($Ident == 'GetAllNodesInformation') {
            if ($Value) {
                if ($this->GetAllNodesInformation()) {
                    while ($this->GetNodeInfoIsRunning) {
                        IPS_Sleep(10);
                    }
                    return true;
                }
            } else {
                return $this->GetAllNodesInformation();
            }
        }
        return false;
    }

    /**
     * Interne Funktion des SDK.
     */
    public function GetConfigurationForParent()
    {
        $Config = [
            \KLF200\ClientSocket\Property::Port      => 51200,
            \KLF200\ClientSocket\Property::UseSSL    => true,
            \KLF200\ClientSocket\Property::VerifyPeer=> false,
            \KLF200\ClientSocket\Property::VerifyHost=> true
        ];
        return json_encode($Config);
    }

    /**
     * Interne Funktion des SDK.
     */
    public function ApplyChanges()
    {
        $this->RegisterMessage($this->InstanceID, FM_CONNECT);
        $this->RegisterMessage($this->InstanceID, FM_DISCONNECT);

        parent::ApplyChanges();
        $this->RegisterVariableString('FirmwareVersion', $this->Translate('Firmware Version'), '', 0);
        $this->RegisterVariableInteger('HardwareVersion', $this->Translate('Hardware Version'), '', 0);
        $this->RegisterVariableString('ProtocolVersion', $this->Translate('Protocol Version'), '', 0);

        $this->ReceiveBuffer = '';
        $this->ReplyAPIData = [];
        $this->Nodes = [];
        if (IPS_GetKernelRunlevel() == KR_READY) {
            $this->RegisterParent();
            if ($this->HasActiveParent()) {
                $this->IOChangeState(IS_ACTIVE);
            } else {
                $this->IOChangeState(IS_INACTIVE);
            }
        }
    }

    public function ReadGatewayState()
    {
        $APIData = new \KLF200\APIData(\KLF200\APICommand::GET_STATE_REQ);
        //$APIData = new \KLF200\APIData(\KLF200\APICommand::GET_SCENE_LIST_REQ);
        $ResultAPIData = $this->SendAPIData($APIData);
        //todo
        // brauchen wir state? Oder substate?
        /*
          Command           Data 1          Data 2      Data 3 – 6
          GW_GET_STATE_CFM  GatewayState    SubState    StateData

          GatewayState value Description
          0 Test mode.
          1 Gateway mode, no actuator nodes in the system table.
          2 Gateway mode, with one or more actuator nodes in the system table.
          3 Beacon mode, not configured by a remote controller.
          4 Beacon mode, has been configured by a remote controller.
          5 - 255 Reserved.

          SubState value, when
          GatewayState is 1 or 2 Description
          0x00 Idle state.
          0x01 Performing task in Configuration Service handler
          0x02 Performing Scene Configuration
          0x03 Performing Information Service Configuration.
          0x04 Performing Contact input Configuration.
          0x?? In Contact input Learn state. ???
          0x80 Performing task in Command Handler
          0x81 Performing task in Activate Group Handler
          0x82 Performing task in Activate Scene Handler
         */
    }

    public function RequestGatewayVersion()
    {
        $APIData = new \KLF200\APIData(\KLF200\APICommand::GET_VERSION_REQ);
        $ResultAPIData = $this->SendAPIData($APIData);
        if ($ResultAPIData->isError()) {
            return false;
        }
        $this->SetValueString('FirmwareVersion', ord($ResultAPIData->Data[1]) . '.' .
                ord($ResultAPIData->Data[2]) . '.' .
                ord($ResultAPIData->Data[3]) . '.' .
                ord($ResultAPIData->Data[4]));
        $this->SetValueInteger('HardwareVersion', ord($ResultAPIData->Data[6]));
        return true;
    }

    public function RequestProtocolVersion()
    {
        $APIData = new \KLF200\APIData(\KLF200\APICommand::GET_PROTOCOL_VERSION_REQ);
        $ResultAPIData = $this->SendAPIData($APIData);
        if ($ResultAPIData->isError()) {
            return false;
        }
        $this->SetValueString(
            'ProtocolVersion',
            unpack('n', substr($ResultAPIData->Data, 0, 2))[1] . '.' .
                unpack('n', substr($ResultAPIData->Data, 2, 2))[1]
        );
        return true;
    }

    public function SetGatewayTime()
    {
        $APIData = new \KLF200\APIData(\KLF200\APICommand::SET_UTC_REQ, pack('N', time()));
        $ResultAPIData = $this->SendAPIData($APIData);
        return !$ResultAPIData->isError();
    }

    public function GetGatewayTime()
    {
        $APIData = new \KLF200\APIData(\KLF200\APICommand::GET_LOCAL_TIME_REQ);
        $ResultAPIData = $this->SendAPIData($APIData);
        if ($ResultAPIData->isError()) {
            return false;
        }
        $Result = [
            'Timestamp'          => unpack('N', substr($ResultAPIData->Data, 0, 4))[1],
            'Second'             => ord($ResultAPIData->Data[4]),
            'Minute'             => ord($ResultAPIData->Data[5]),
            'Hour'               => ord($ResultAPIData->Data[6]),
            'DayOfMonth'         => ord($ResultAPIData->Data[7]),
            'Month'              => 1 + ord($ResultAPIData->Data[8]),
            'Year'               => 1900 + unpack('n', substr($ResultAPIData->Data, 9, 2))[1],
            'WeekDay'            => ord($ResultAPIData->Data[11]),
            'DayOfYear'          => unpack('n', substr($ResultAPIData->Data, 12, 2))[1],
            'DaylightSavingFlag' => unpack('c', $ResultAPIData->Data[14])[1]
        ];
        return $Result;
    }

    //################# DATAPOINTS CHILDREN

    /**
     * Interne Funktion des SDK. Nimmt Daten von Children entgegen und sendet Diese weiter.
     *
     * @param string $JSONString
     * @result bool true wenn Daten gesendet werden konnten, sonst false.
     */
    public function ForwardData($JSONString)
    {
        if ($this->GetStatus() != IS_ACTIVE) {
            return serialize(new \KLF200\APIData(\KLF200\APICommand::ERROR_NTF, chr(\KLF200\ErrorNTF::TIMEOUT)));
        }
        $APIData = new \KLF200\APIData($JSONString);
        $result = @$this->SendAPIData($APIData);
        return serialize($result);
    }

    //################# DATAPOINTS PARENT

    /**
     * Empfängt Daten vom Parent.
     *
     * @param string $JSONString Das empfangene JSON-kodierte Objekt vom Parent.
     * @result bool True wenn Daten verarbeitet wurden, sonst false.
     */
    public function ReceiveData($JSONString)
    {
        $data = json_decode($JSONString);
        $this->DecodeSLIPData(utf8_decode($data->Buffer));
    }

    public function RebootGateway()
    {
        $APIData = new \KLF200\APIData(\KLF200\APICommand::REBOOT_REQ);
        $ResultAPIData = $this->SendAPIData($APIData);
        return !$ResultAPIData->isError();
    }

    /**
     * Wird ausgeführt wenn der Kernel hochgefahren wurde.
     */
    protected function KernelReady()
    {
        $this->ReceiveBuffer = '';
        $this->ReplyAPIData = [];
        $this->Nodes = [];
        $this->RegisterParent();
        if ($this->HasActiveParent()) {
            $this->IOChangeState(IS_ACTIVE);
        } else { // Parent nicht aktiv. Bei KernelReady sollte das normal sein, da wir den IO beim beenden schließen.
            if ($this->ParentID) { // Haben wir einen Parent?
                $CurrentState = @IPS_GetProperty($this->ParentID, 'Open'); // ist der auch wirklich geschlossen?
                // War er vor dem Shutdown geöffnet?
                $LastState = $this->ReadAttributeBoolean(\KLF200\Gateway\Attribute::ClientSocketStateOnShutdown);
                if ($LastState && !$CurrentState) {
                    // Dann den IO öffnen. Das wird anschließend IM_CHANGESTATUS trigger (landet in IOChangeState), so das wir hier fertig sind.
                    IPS_RunScriptText('IPS_SetProperty(' . $this->ParentID . ', \'Open\', true); IPS_ApplyChanges(' . $this->ParentID . ');');
                    return;
                }
            }
            // wir haben keinen Parent.. also INACTIVE Status
            $this->IOChangeState(IS_INACTIVE);
        }
    }

    /**
     * Wird ausgeführt wenn der Kernel runtergefahren wurde.
     */
    protected function KernelShutdown()
    {
        if ($this->ParentID) {
            // Open/Closed vom IO merken, wir müssen die Verbindung sauber trennen und beim neustart gezielt aufbauen, sonst schmiert das KLF200 ab.
            $LastState = @IPS_GetProperty($this->ParentID, 'Open');
            $this->WriteAttributeBoolean(
                \KLF200\Gateway\Attribute::ClientSocketStateOnShutdown,
                $LastState
            );
            if ($LastState) {
                if ($this->ReadPropertyBoolean(\KLF200\Gateway\Property::RebootOnShutdown)) {
                    $this->RebootGateway();
                }
                // IO war geöffnet, wir haben uns das in einem Attribute gemerkt und schließen jetzt den IO.
                IPS_RunScriptText('IPS_SetProperty(' . $this->ParentID . ', \'Open\', false); IPS_ApplyChanges(' . $this->ParentID . ');');
            }
        }
    }
    protected function RegisterParent()
    {
        $IOId = $this->IORegisterParent();
        if ($IOId > 0) {
            $this->Host = IPS_GetProperty($this->ParentID, 'Host');
            $this->SetSummary(IPS_GetProperty($IOId, 'Host'));
        } else {
            $this->Host = '';
            $this->SetSummary(('none'));
        }
        return $IOId;
    }

    /**
     * Wird über den Trait InstanceStatus ausgeführt wenn sich der Status des Parent ändert.
     * Oder wenn sich die Zuordnung zum Parent ändert.
     *
     * @param int $State Der neue Status des Parent.
     */
    protected function IOChangeState($State)
    {
        if ($State == IS_ACTIVE) {
            if ($this->Connect()) {
                $this->SetTimerInterval(\KLF200\Gateway\Timer::KeepAlive, 300000);
                $this->LogMessage($this->Translate('Successfully connected to KLF200.'), KL_NOTIFY);
                $this->RequestProtocolVersion();
                $this->SetGatewayTime();
                $this->ReadGatewayState();
                $this->RequestGatewayVersion();
                $this->SetHouseStatusMonitor();
                $this->GetAllNodesInformation();
            } else {
                $this->SetTimerInterval(\KLF200\Gateway\Timer::KeepAlive, 0);
            }
        } else {
            $this->SetTimerInterval(\KLF200\Gateway\Timer::KeepAlive, 0);
            $this->SetStatus(IS_INACTIVE);
        }
    }

    protected function SendDebug($Message, $Data, $Format)
    {
        if (is_a($Data, '\\KLF200\\APIData')) {
            /** @var \KLF200\APIData $Data */
            $this->SendDebugTrait($Message . ':Command', \KLF200\APICommand::ToString($Data->Command), 0);
            if ($Data->NodeID != -1) {
                $this->SendDebugTrait($Message . ':NodeID', $Data->NodeID, 0);
            }
            if ($Data->isError()) {
                $this->SendDebugTrait('Error', $Data->ErrorToString(), 0);
            } elseif ($Data->Data != '') {
                $this->SendDebugTrait($Message . ':Data', $Data->Data, $Format);
            }
        } else {
            $this->SendDebugTrait($Message, $Data, $Format);
        }
    }

    private function GetAllNodesInformation()
    {
        $this->Nodes = [];

        $APIData = new \KLF200\APIData(\KLF200\APICommand::GET_ALL_NODES_INFORMATION_REQ);
        $ResultAPIData = $this->SendAPIData($APIData);
        if ($ResultAPIData->isError()) {
            return false;
        }
        $this->GetNodeInfoIsRunning = true;
        return ord($ResultAPIData->Data[0]) == 1;
    }

    /*
      public function GetSceneList()
      {
      $APIData = new \KLF200\APIData(\KLF200\APICommand::GET_SCENE_LIST_REQ);
      $ResultAPIData = $this->SendAPIData($APIData);
      }
     */

    private function SetHouseStatusMonitor()
    {
        $APIData = new \KLF200\APIData(\KLF200\APICommand::HOUSE_STATUS_MONITOR_ENABLE_REQ);
        $ResultAPIData = $this->SendAPIData($APIData, false);
        return !$ResultAPIData->isError();
    }

    //################# PRIVATE

    private function ReceiveEvent(\KLF200\APIData $APIData)
    {
        switch ($APIData->Command) {
            case \KLF200\APICommand::CS_DISCOVER_NODES_NTF:
                sleep(3);
                if (!$this->GetNodeInfoIsRunning) {
                    IPS_RunScriptText('IPS_RequestAction(' . $this->InstanceID . ',"GetAllNodesInformation",false);');
                }
                break;
            case \KLF200\APICommand::CS_SYSTEM_TABLE_UPDATE_NTF:
                sleep(3);
                if (!$this->GetNodeInfoIsRunning) {
                    IPS_RunScriptText('IPS_RequestAction(' . $this->InstanceID . ',"GetAllNodesInformation",false);');
                }
                break;
            case \KLF200\APICommand::GET_ALL_NODES_INFORMATION_FINISHED_NTF:
                $this->GetNodeInfoIsRunning = false;
                break;
        }
        $this->SendAPIDataToChildren($APIData);
    }

    /**
     * Connect
     *
     * @return bool
     */
    private function Connect()
    {
        if (strlen($this->ReadPropertyString(\KLF200\Gateway\Property::Password)) > 31) {
            $this->SetStatus(IS_EBASE + 4);
            return false;
        }

        $APIData = new \KLF200\APIData(\KLF200\APICommand::PASSWORD_ENTER_REQ, str_pad($this->ReadPropertyString(\KLF200\Gateway\Property::Password), 32, "\x00"));
        $ResultAPIData = $this->SendAPIData($APIData, false);
        if ($ResultAPIData === false) {
            $this->SetStatus(IS_EBASE + 2);
            return false;
        }
        if ($ResultAPIData->isError()) {
            $this->SetStatus(IS_EBASE + 3);
            trigger_error($this->Translate($ResultAPIData->ErrorToString()), E_USER_NOTICE);
            return false;
        }
        if ($ResultAPIData->Data != "\x00") {
            $this->SendDebug('Login Error', '', 0);
            $this->SetStatus(IS_EBASE + 1);
            $this->LogMessage('Access denied', KL_ERROR);
            return false;
        }
        $this->SendDebug('Login successfully', '', 0);
        $this->SetStatus(IS_ACTIVE);
        return true;
    }

    /**
     * Sendet die Events an die Children.
     *
     * @param \KLF200\APIData $APIData
     */
    private function SendAPIDataToChildren(\KLF200\APIData $APIData)
    {
        $this->SendDataToChildren($APIData->ToJSON(\KLF200\GUID::ToNodes));
    }

    private function DecodeSLIPData($SLIPData)
    {
        $SLIPData = $this->ReceiveBuffer . $SLIPData;
        //$this->SendDebug('Input SLIP Data', $SLIPData, 1);
        $Start = strpos($SLIPData, chr(0xc0));
        if ($Start === false) {
            $this->SendDebug('ERROR', 'SLIP Start Marker not found', 0);
            $this->ReceiveBuffer = '';
            return false;
        }
        if ($Start != 0) {
            $this->SendDebug('WARNING', 'SLIP start is ' . $Start . ' and not 0', 0);
        }
        $End = strpos($SLIPData, chr(0xc0), 1);
        if ($End === false) {
            $this->SendDebug('WAITING', 'SLIP End Marker not found', 0);
            $this->ReceiveBuffer = $SLIPData;
            return false;
        }
        $TransportData = str_replace(
            ["\xDB\xDC", "\xDB\xDD"],
            ["\xC0", "\xDB"],
            substr($SLIPData, $Start + 1, $End - $Start - 1)
        );
        $Tail = substr($SLIPData, $End + 1);
        $this->ReceiveBuffer = $Tail;
        if (ord($TransportData[0]) != 0) {
            $this->SendDebug('ERROR', 'Wrong ProtocolID', 0);
            return false;
        }
        $len = ord($TransportData[1]) + 2;
        if (strlen($TransportData) != $len) {
            $this->SendDebug('ERROR', 'Wrong frame length', 0);
            return false;
        }
        $Checksum = substr($TransportData, -1);
        $ChecksumData = substr($TransportData, 0, -1);
        //todo Checksum
        $Command = unpack('n', substr($TransportData, 2, 2))[1];
        $Data = substr($TransportData, 4, $len - 5);
        $APIData = new \KLF200\APIData($Command, $Data);
        if ($APIData->isEvent()) {
            $this->SendDebug('Event', $APIData, 1);
            $this->ReceiveEvent($APIData);
        } else {
            $this->SendQueueUpdate($APIData);
        }
        if (strpos($Tail, chr(0xc0)) !== false) {
            $this->SendDebug('Tail hast Start Marker', '', 0);
            $this->DecodeSLIPData('');
        }
    }

    /**
     * Wartet auf eine Antwort einer Anfrage an den LMS.
     *
     * @param string $APICommand
     * @return ?\KLF200\APIData
     */
    /*
    private function ReadReplyAPIData()
    {
        for ($i = 0; $i < 2000; $i++) {
            $Buffer = $this->ReplyAPIData;
            if (!is_null($Buffer)) {
                $this->ReplyAPIData = null;
                return $Buffer;
            }
            usleep(1000);
        }
        return null;
    }
     */
    /**
     * SendAPIData
     *
     * @param  \KLF200\APIData $APIData
     * @param  bool $SetState
     * @return \KLF200\APIData
     */
    private function SendAPIData(\KLF200\APIData $APIData, bool $SetState = true)
    {
        try {
            $this->SendDebug('Wait to send', \KLF200\APICommand::ToString($APIData->Command), 0);
            $time = microtime(true);

            while (true) {
                if ($this->lock('SendAPIData')) {
                    break;
                }
                if (microtime(true) - $time > 5) {
                    throw new Exception($this->Translate('Send is blocked for: ') . \KLF200\APICommand::ToString($APIData->Command), E_USER_ERROR);
                }
            }
            if (!$this->HasActiveParent()) {
                $this->unlock('SendAPIData');
                throw new Exception($this->Translate('Socket not connected'), E_USER_NOTICE);
            }
            $Data = $APIData->GetSLIPData();
            $this->SendDebug('Send', $APIData, 1);
            //$this->SendDebug('Send SLIP Data', $Data, 1);
            $JSON['DataID'] = \KLF200\GUID::ToClientSocket;
            $JSON['Buffer'] = utf8_encode($Data);
            $JsonString = json_encode($JSON);
            $this->SendQueueAdd($APIData->Command);
            parent::SendDataToParent($JsonString);
            $this->unlock('SendAPIData');
            $ResponseAPIData = $this->SendQueueWaitForResponse($APIData->Command);
            if ($ResponseAPIData === null) {
                throw new Exception($this->Translate('Timeout.'), E_USER_NOTICE);
            }
            $this->SendDebug('Response', $ResponseAPIData, 1);
            $this->SendDebug('Duration', (int) ((microtime(true) - $time) * 1000) . ' msec', 0);
            if ($ResponseAPIData->isError()) {
                trigger_error($this->Translate($ResponseAPIData->ErrorToString()), E_USER_NOTICE);
            }
            return $ResponseAPIData;
        } catch (Exception $exc) {
            $this->SendDebug('Error', $exc->getMessage(), 0);
            trigger_error($this->Translate($exc->getMessage()), E_USER_NOTICE);
            if ($SetState) {
                $this->SetStatus(IS_EBASE + 3);
            }
            $ResponseAPIData = new \KLF200\APIData(\KLF200\APICommand::ERROR_NTF, chr(\KLF200\ErrorNTF::TIMEOUT));
        }
        return $ResponseAPIData;
    }

    //################# SendQueue
    private function SendQueueAdd(int $APIDataCommand)
    {
        $APIDataCommand++;
        $this->lock('ReplyAPIData');
        $ReplyAPIData = $this->ReplyAPIData;
        $ReplyAPIData[$APIDataCommand] = null;
        $this->ReplyAPIData = $ReplyAPIData;
        $this->unlock('ReplyAPIData');
    }

    private function SendQueueUpdate(\KLF200\APIData $APIData)
    {
        $this->lock('ReplyAPIData');
        $ReplyAPIData = $this->ReplyAPIData;
        if (!array_key_exists($APIData->Command, $ReplyAPIData)) {
            $this->unlock('ReplyAPIData');
            return false;
        }
        $ReplyAPIData[$APIData->Command] = $APIData;

        $this->ReplyAPIData = $ReplyAPIData;
        $this->unlock('ReplyAPIData');
        return true;
    }

    private function SendQueueWaitForResponse(int $APIDataCommand)
    {
        $APIDataCommand++;
        for ($i = 0; $i < 1200; $i++) {
            $this->lock('ReplyAPIData');
            $ReplyAPIData = $this->ReplyAPIData;
            $this->unlock('ReplyAPIData');
            if (!array_key_exists($APIDataCommand, $ReplyAPIData)) {
                $this->SendDebug('Error in SendQueueWait', \KLF200\APICommand::ToString($APIDataCommand), 0);
                return null;
            }
            if (is_a($ReplyAPIData[$APIDataCommand], '\\KLF200\\APIData')) {
                $this->SendQueueRemove($APIDataCommand);
                return $ReplyAPIData[$APIDataCommand];
            }
            IPS_Sleep(5);
        }
        $this->SendQueueRemove($APIDataCommand);
        return null;
    }

    private function SendQueueRemove(int $APIDataCommand)
    {
        $this->lock('ReplyAPIData');
        $ReplyAPIData = $this->ReplyAPIData;
        if (array_key_exists($APIDataCommand, $ReplyAPIData)) {
            unset($ReplyAPIData[$APIDataCommand]);
            $this->ReplyAPIData = $ReplyAPIData;
        }
        $this->unlock('ReplyAPIData');
    }
}
