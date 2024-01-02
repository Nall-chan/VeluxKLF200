<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/KLF200Class.php';  // diverse Klassen
eval('declare(strict_types=1);namespace KLF200Gateway {?>' . file_get_contents(__DIR__ . '/../libs/helper/BufferHelper.php') . '}');
eval('declare(strict_types=1);namespace KLF200Gateway {?>' . file_get_contents(__DIR__ . '/../libs/helper/SemaphoreHelper.php') . '}');
eval('declare(strict_types=1);namespace KLF200Gateway {?>' . file_get_contents(__DIR__ . '/../libs/helper/DebugHelper.php') . '}');
eval('declare(strict_types=1);namespace KLF200Gateway {?>' . file_get_contents(__DIR__ . '/../libs/helper/ParentIOHelper.php') . '}');
eval('declare(strict_types=1);namespace KLF200Gateway {?>' . file_get_contents(__DIR__ . '/../libs/helper/VariableHelper.php') . '}');

/*
 * @addtogroup klf200
 * @{
 *
 * @package       KLF200
 * @file          module.php
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2020 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 * @version       1.0
 */

/**
 * KLF200Gateway Klasse implementiert die KLF 200 API
 * Erweitert IPSModule.
 *
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2020 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 *
 * @version       1.0
 *
 * @example <b>Ohne</b>
 *
 * @property string $Host
 * @property string $ReceiveBuffer
 * @property APIData $ReceiveAPIData
 * @property APIData $ReplyAPIData
 * @property array $Nodes
 * @property int $WaitForNodes
 * @property int $SessionId
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
            \KLF200Gateway\DebugHelper::SendDebug as SendDebug2;
        }

    /**
     * Interne Funktion des SDK.
     */
    public function Create()
    {
        parent::Create();
        $this->RequireParent('{3CFF0FD9-E306-41DB-9B5A-9D06D38576C3}');
        $this->RegisterPropertyString('Password', '');
        $this->RegisterTimer('KeepAlive', 0, 'KLF200_ReadGatewayState($_IPS[\'TARGET\']);');
        $this->Host = '';
        $this->ReceiveBuffer = '';
        $this->ReplyAPIData = null;
        $this->Nodes = [];
        $this->SessionId = 1;
        $this->ParentID = 0;
    }

    /**
     * Interne Funktion des SDK.
     */
    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        $this->IOMessageSink($TimeStamp, $SenderID, $Message, $Data);
        switch ($Message) {
            case IPS_KERNELSTARTED:
                $this->KernelReady();
                break;
                //        case IPS_KERNELSHUTDOWN:
                //$this->SendDisconnect();
                // Todo
                //            break;
        }
    }

    public function RequestAction($Ident, $Value)
    {
        if ($this->IORequestAction($Ident, $Value)) {
            return true;
        }
        return false;
    }

    /**
     * Interne Funktion des SDK.
     */
    public function GetConfigurationForParent()
    {
        $Config = [
            'Port'      => 51200,
            'UseSSL'    => true,
            'VerifyPeer'=> false,
            'VerifyHost'=> true
        ];
        return json_encode($Config);
    }

    /**
     * Interne Funktion des SDK.
     */
    public function ApplyChanges()
    {
        $this->RegisterMessage(0, IPS_KERNELSTARTED);
        $this->RegisterMessage(0, IPS_KERNELSHUTDOWN);

        $this->RegisterMessage($this->InstanceID, FM_CONNECT);
        $this->RegisterMessage($this->InstanceID, FM_DISCONNECT);

        parent::ApplyChanges();
        $this->RegisterVariableString('FirmwareVersion', $this->Translate('Firmware Version'), '', 0);
        $this->RegisterVariableInteger('HardwareVersion', $this->Translate('Hardware Version'), '', 0);
        $this->RegisterVariableString('ProtocolVersion', $this->Translate('Protocol Version'), '', 0);
        if (IPS_GetKernelRunlevel() == KR_READY) {
            $this->KernelReady();
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
        $this->ReplyAPIData = null;
        $this->Nodes = [];
        $this->RegisterParent();
        if ($this->HasActiveParent()) {
            $this->IOChangeState(IS_ACTIVE);
        }  else {
            $this->IOChangeState(IS_INACTIVE);
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
                $this->SetTimerInterval('KeepAlive', 600000);
                $this->LogMessage($this->Translate('Successfully connected to KLF200.'), KL_NOTIFY);
                $this->SessionId = 1;
                $this->RequestProtocolVersion();
                $this->SetGatewayTime();
                $this->ReadGatewayState();
                $this->RequestGatewayVersion();
                $this->SetHouseStatusMonitor();
            } else {
                $this->SetTimerInterval('KeepAlive', 0);
            }
        } else {
            $this->SetTimerInterval('KeepAlive', 0);
            $this->SetStatus(IS_INACTIVE);
        }
    }

    protected function SendDebug($Message, $Data, $Format)
    {
        if (is_a($Data, '\\KLF200\\APIData')) {
            /* @var $Data \KLF200\APIData */
            $this->SendDebug2($Message . ':Command', \KLF200\APICommand::ToString($Data->Command), 0);
            if ($Data->isError()) {
                $this->SendDebug2('Error', $Data->ErrorToString(), 0);
            } elseif ($Data->Data != '') {
                $this->SendDebug2($Message . ':Data', $Data->Data, $Format);
            }
        } else {
            $this->SendDebug2($Message, $Data, $Format);
        }
    }

    /*
      public function GetSystemTable()
      {
      $APIData = new \KLF200\APIData(\KLF200\APICommand::CS_GET_SYSTEMTABLE_DATA_REQ);
      $ResultAPIData = $this->SendAPIData($APIData);
      //$this->lock('SendAPIData');
      // wait for finish
      // 01 00 3A DC 1C 03 C0 1C 01 00 00 00 00
      //$this->unlock('SendAPIData');
      }

      public function GetSceneList()
      {
      $APIData = new \KLF200\APIData(\KLF200\APICommand::GET_SCENE_LIST_REQ);
      $ResultAPIData = $this->SendAPIData($APIData);
      }
     */
    private function SetHouseStatusMonitor()
    {
        $APIData = new \KLF200\APIData(\KLF200\APICommand::HOUSE_STATUS_MONITOR_ENABLE_REQ);
        $ResultAPIData = $this->SendAPIData($APIData);
        return !$ResultAPIData->isError();
    }

    //################# PRIVATE
    private function ReceiveEvent(\KLF200\APIData $APIData)
    {
        $this->SendAPIDataToChildren($APIData);
    }

    private function Connect()
    {
        if (strlen($this->ReadPropertyString('Password')) > 31) {
            $this->SetStatus(IS_EBASE + 4);
            return false;
        }

        $APIData = new \KLF200\APIData(\KLF200\APICommand::PASSWORD_ENTER_REQ, str_pad($this->ReadPropertyString('Password'), 32, "\x00"));
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
        $this->SendDataToChildren($APIData->ToJSON('{5242DAEF-EEBD-441F-AB0B-E83C01475B65}'));
    }

    private function DecodeSLIPData($SLIPData)
    {
        $SLIPData = $this->ReceiveBuffer . $SLIPData;
        $this->SendDebug('Input SLIP Data', $SLIPData, 1);
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
            $this->ReplyAPIData = $APIData;
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
     * @result mixed
     */
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

    //################# SENDQUEUE

    private function GetSessionId()
    {
        $SessionId = ($this->SessionId + 1) & 0xffff;
        $this->SessionId = $SessionId;
        return pack('n', $SessionId);
    }

    private function SendAPIData(\KLF200\APIData $APIData, bool $SetState = true)
    {
        //Statt SessionId benutzen wir einfach NodeID.
        /* if (in_array($APIData->Command, [
          \KLF200\APICommand::COMMAND_SEND_REQ,
          \KLF200\APICommand::STATUS_REQUEST_REQ,
          \KLF200\APICommand::WINK_SEND_REQ,
          \KLF200\APICommand::SET_LIMITATION_REQ,
          \KLF200\APICommand::GET_LIMITATION_STATUS_REQ,
          \KLF200\APICommand::MODE_SEND_REQ,
          \KLF200\APICommand::ACTIVATE_SCENE_REQ,
          \KLF200\APICommand::STOP_SCENE_REQ,
          \KLF200\APICommand::ACTIVATE_PRODUCTGROUP_REQ
          ])) {
          $APIData->Data = $this->GetSessionId() . $APIData->Data;
          } */
        try {
            $this->SendDebug('Wait to send', $APIData, 1);
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
                throw new Exception($this->Translate('Socket not connected'), E_USER_NOTICE);
            }
            $Data = $APIData->GetSLIPData();
            $this->SendDebug('Send', $APIData, 1);
            $this->SendDebug('Send SLIP Data', $Data, 1);
            $JSON['DataID'] = '{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}';
            $JSON['Buffer'] = utf8_encode($Data);
            $JsonString = json_encode($JSON);
            $this->ReplyAPIData = null;
            parent::SendDataToParent($JsonString);
            $ResponseAPIData = $this->ReadReplyAPIData();

            if ($ResponseAPIData === null) {
                throw new Exception($this->Translate('Timeout.'), E_USER_NOTICE);
            }
            $this->SendDebug('Response', $ResponseAPIData, 1);
            $this->unlock('SendAPIData');
            if ($ResponseAPIData->isError()) {
                trigger_error($this->Translate($ResponseAPIData->ErrorToString()), E_USER_NOTICE);
            }
            return $ResponseAPIData;
        } catch (Exception $exc) {
            $this->SendDebug('Error', $exc->getMessage(), 0);
            if ($exc->getCode() != E_USER_ERROR) {
                $this->unlock('SendAPIData');
            }
            trigger_error($this->Translate($exc->getMessage()), E_USER_NOTICE);
            if ($SetState) {
                $this->SetStatus(IS_EBASE + 3);
            }
            return new \KLF200\APIData(\KLF200\APICommand::ERROR_NTF, chr(\KLF200\ErrorNTF::TIMEOUT));
        }
    }
}

/* @} */
