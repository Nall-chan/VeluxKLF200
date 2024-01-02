<?php

declare(strict_types=1);
require_once __DIR__ . '/../libs/KLF200Class.php';  // diverse Klassen
eval('declare(strict_types=1);namespace KLF200Node {?>' . file_get_contents(__DIR__ . '/../libs/helper/BufferHelper.php') . '}');
eval('declare(strict_types=1);namespace KLF200Node {?>' . file_get_contents(__DIR__ . '/../libs/helper/SemaphoreHelper.php') . '}');
eval('declare(strict_types=1);namespace KLF200Node {?>' . file_get_contents(__DIR__ . '/../libs/helper/DebugHelper.php') . '}');
eval('declare(strict_types=1);namespace KLF200Node {?>' . file_get_contents(__DIR__ . '/../libs/helper/VariableHelper.php') . '}');
eval('declare(strict_types=1);namespace KLF200Node {?>' . file_get_contents(__DIR__ . '/../libs/helper/VariableProfileHelper.php') . '}');

/**
 * @property char $NodeId
 * @property int $SessionId
 * @property int $NodeSubType
 */
class KLF200Node extends IPSModule
{
    use \KLF200Node\Semaphore,
        \KLF200Node\BufferHelper,
        \KLF200Node\VariableHelper,
        \KLF200Node\VariableProfileHelper,
        \KLF200Node\DebugHelper {
            \KLF200Node\DebugHelper::SendDebug as SendDebug2;
        }

    /**
     * Interne Funktion des SDK.
     */
    public function Create()
    {
        parent::Create();
        $this->ConnectParent('{725D4DF6-C8FC-463C-823A-D3481A3D7003}');
        $this->RegisterPropertyInteger('NodeId', -1);
        $this->RegisterAttributeInteger('NodeSubType', -1);
        $this->SessionId = 1;
        $this->NodeSubType = -1;
    }

    /**
     * Interne Funktion des SDK.
     */
    public function ApplyChanges()
    {
        parent::ApplyChanges();
        $APICommands = [
            \KLF200\APICommand::GET_NODE_INFORMATION_NTF,
            \KLF200\APICommand::NODE_INFORMATION_CHANGED_NTF,
            \KLF200\APICommand::NODE_STATE_POSITION_CHANGED_NTF,
            \KLF200\APICommand::COMMAND_RUN_STATUS_NTF,
            \KLF200\APICommand::COMMAND_REMAINING_TIME_NTF,
            \KLF200\APICommand::SESSION_FINISHED_NTF,
            \KLF200\APICommand::STATUS_REQUEST_NTF,
            \KLF200\APICommand::WINK_SEND_NTF,
            \KLF200\APICommand::MODE_SEND_NTF
        ];
        $this->SessionId = 1;
        $NodeId = $this->ReadPropertyInteger('NodeId');
        $this->NodeId = chr($NodeId);
        if (($NodeId < 0) || ($NodeId > 255)) {
            $Line = 'NOTHING';
        } else {
            $NodeId = preg_quote(substr(json_encode(utf8_encode(chr($this->ReadPropertyInteger('NodeId')))), 0, -1));
            foreach ($APICommands as $APICommand) {
                $Lines[] = '.*"Command":' . $APICommand . ',"Data":' . $NodeId . '.*';
            }
            $Line = implode('|', $Lines);
        }
        $this->SetReceiveDataFilter('(' . $Line . ')');
        $this->SendDebug('FILTER', $Line, 0);
        $this->NodeSubType = $this->ReadAttributeInteger('NodeSubType');
        $this->SetSummary(sprintf('%04X', $this->NodeSubType));
        $this->RegisterProfileInteger('KLF200.Intensity.51200', '', '', ' %', 0, 0xC800, 1);
        $this->RegisterProfileInteger('KLF200.RollerShutter', 'Jalousie', '', ' %', 0, 0xC800, 1);
        $this->RegisterProfileInteger('KLF200.Slats', 'Speedo', '', ' %', 0, 0xC800, 1);
        $this->RegisterProfileInteger('KLF200.Blind', 'Raffstore', '', ' %', 0, 0xC800, 1);
        $this->RegisterProfileInteger('KLF200.Window', 'Window', '', ' %', 0, 0xC800, 1);
        $this->RegisterProfileInteger('KLF200.Heating.Reversed', 'Temperature', '', ' %', 0, 0xC800, 1);
        $this->RegisterProfileInteger('KLF200.Garage', 'Garage', '', ' %', 0, 0xC800, 1);
        $this->RegisterProfileInteger('KLF200.Light.51200.Reversed', 'Light', '', ' %', 0, 0xC800, 1);
        $this->RegisterProfileBoolean('KLF200.Light.Reversed', 'Light', '', '');
        $this->RegisterProfileBoolean('KLF200.Lock', 'Lock', '', '');
        if (IPS_GetKernelRunlevel() == KR_READY) {
            $this->RequestNodeInformation();
        }
    }

    public function SwitchMode(bool $Value)
    {
        return $this->SetMainParameter($Value ? 0xC800 : 0x0000);
    }

    public function ShutterMove(int $Value)
    {
        switch ($this->NodeSubType) {
            case 0x0040: //Interior Venetian Blind
            case 0x0080: //Roller Shutter
            case 0x0081: //Adjustable slats rolling shutter
            case 0x0082: //Roller Shutter With projection
            case 0x00C0: //Vertical Exterior Awning
            case 0x0100: //Window opener
            case 0x0101: //Window opener with integrated rain sensor
            case 0x0140: //Garage door opener
            case 0x017A: //Garage door opener
            case 0x0200: //Rolling Door Opener
            case 0x01C0: //Gate opener
            case 0x01FA: //Gate opener
            case 0x0280: //Vertical Interior Blinds
            case 0x0340: //Dual Roller Shutter
            case 0x0400: //Horizontal awning
            case 0x04C0: //Curtain track
            case 0x0600: //Swinging Shutters
            case 0x0601: //Swinging Shutter with independent handling of the leaves
            case 0x0440: //Exterior Venetian blind
            case 0x0480: //Louver blind
            case 0x0500: //Ventilation point
            case 0x0501: //Air inlet
            case 0x0502: //Air transfer
            case 0x0503: //Air outlet
                return $this->SetMainParameter($Value);
        }
        trigger_error($this->Translate('Instance does not implement this function'), E_USER_NOTICE);
        return false;
    }

    public function ShutterMoveUp()
    {
        return $this->SetMainParameter(0x0000);
    }

    public function ShutterMoveDown()
    {
        return $this->SetMainParameter(0xC800);
    }

    public function ShutterMoveStop()
    {
        return $this->SetMainParameter(0xD200);
    }

    public function OrientationSet(int $Value)
    {
        switch ($this->NodeSubType) {
            case 0x0040:
                return $this->SetFunctionParameter1($Value);
            case 0x0440:
            case 0x0081:
            case 0x0480:
                return $this->SetFunctionParameter3($Value);
        }
        trigger_error($this->Translate('Instance does not implement this function'), E_USER_NOTICE);
        return false;
    }

    public function OrientationUp()
    {
        return $this->SetOrientation(0x0000);
    }

    public function OrientationDown()
    {
        return $this->SetOrientation(0xC800);
    }

    public function OrientationStop()
    {
        return $this->SetOrientation(0xD200);
    }

    public function DimSet(int $Value)
    {
        switch ($this->NodeSubType) {
            case 0x0180: //Light
            case 0x0540: //Exterior heating
            case 0x057A: //Exterior heating
                return $this->SetMainParameter($Value);
        }
        trigger_error($this->Translate('Instance does not implement this function'), E_USER_NOTICE);
        return false;
    }

    public function DimUp()
    {
        return $this->DimSet(0x0000);
    }

    public function DimDown()
    {
        return $this->DimSet(0xC800);
    }

    public function DimStop()
    {
        return $this->DimSet(0xD200);
    }

    public function RequestAction($Ident, $Value)
    {
        if (IPS_GetVariable($this->GetIDForIdent($Ident))['VariableType'] == VARIABLETYPE_BOOLEAN) {
            $Value = $Value ? 0xC800 : 0x0000;
        }
        switch ($Ident) {
            case 'MAIN':
                return $this->SetMainParameter($Value);
            case 'FP1':
                return $this->SetFunctionParameter1($Value);
            case 'FP2':
                return $this->SetFunctionParameter2($Value);
            case 'FP3':
                return $this->SetFunctionParameter3($Value);
        }
        echo $this->Translate('Invalid Ident');
        return;
    }

    public function RequestNodeInformation()
    {
        $APIData = new \KLF200\APIData(\KLF200\APICommand::GET_NODE_INFORMATION_REQ, $this->NodeId);
        $ResultAPIData = $this->SendAPIData($APIData);
        if ($ResultAPIData === null) {
            return false;
        }
        $State = ord($ResultAPIData->Data[0]);
        switch ($State) {
            case 0:
                return true;
            case 1:
                trigger_error($this->Translate('Request rejected'), E_USER_NOTICE);
                return false;
            case 2:
                trigger_error($this->Translate('Invalid node index'), E_USER_NOTICE);
                return false;
        }
    }

    public function RequestStatus()
    {
        /*
          Command               Data 1 – 2 Data 3          Data 4 – 23   Data 24
          GW_STATUS_REQUEST_REQ SessionID  IndexArrayCount IndexArray    StatusType
          Data 25    Data 26
          FPI1       FPI2
          StatusType
          value     |Description
          0       |Request Target position
          1       |Request Current position
          2       |Request Remaining time
          3       |Request Main info.
         */
        $Data = $this->NodeId . $this->GetSessionId() . chr(1) . $this->NodeId . "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00";
        $Data .= chr(1) . chr(0b11100000) . chr(0);
        $APIData = new \KLF200\APIData(\KLF200\APICommand::STATUS_REQUEST_REQ, $Data);
        $ResultAPIData = $this->SendAPIData($APIData);
        if ($ResultAPIData === null) {
            return false;
        }
        return ord($ResultAPIData->Data[2]) == 1;
    }

    public function SetMainParameter(int $Value)
    {
        /*
          Command               Data 1 – 2  Data 3              Data 4          Data 5
          GW_COMMAND_SEND_REQ   SessionID   CommandOriginator   PriorityLevel   ParameterActive
          Data 6    Data 7  Data 8 - 41                     Data 42         Data 43 – 62    Data 63
          FPI1      FPI2    FunctionalParameterValueArray   IndexArrayCount IndexArray      PriorityLevelLock
          Data 64   Data 65 Data 66
          PL_0_3    PL_4_7  LockTime
         */
        $Data = $this->NodeId . $this->GetSessionId(); //Data 1-2
        $Data .= chr(1) . chr(3) . chr(0); // Data 3-5
        $Data .= chr(0) . chr(0); // Data 6-7
        $Data .= pack('n', $Value); // Data 8-9
        $Data .= "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00"; // Data 10-25
        $Data .= "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00"; // Data 26-41
        $Data .= chr(1) . $this->NodeId . "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00"; //Data 42-62
        $Data .= chr(0); // Data 63
        $Data .= chr(0) . chr(0) . chr(0); // Data 64-66
        $APIData = new \KLF200\APIData(\KLF200\APICommand::COMMAND_SEND_REQ, $Data);
        $ResultAPIData = $this->SendAPIData($APIData);
        if ($ResultAPIData === null) {
            return false;
        }
        return ord($ResultAPIData->Data[2]) == 1;
    }

    public function SetFunctionParameter1(int $Value)
    {
        /*
          Command               Data 1 – 2  Data 3              Data 4          Data 5
          GW_COMMAND_SEND_REQ   SessionID   CommandOriginator   PriorityLevel   ParameterActive
          Data 6    Data 7  Data 8 - 41                     Data 42         Data 43 – 62    Data 63
          FPI1      FPI2    FunctionalParameterValueArray   IndexArrayCount IndexArray      PriorityLevelLock
          Data 64   Data 65 Data 66
          PL_0_3    PL_4_7  LockTime
         */
        $Data = $this->NodeId . $this->GetSessionId(); //Data 1-2
        $Data .= chr(1) . chr(3) . chr(1); // Data 3-5
        $Data .= chr(0x80) . chr(0); // Data 6-7
        $Data .= "\xD4\x00"; // Data 8-9 -> ignore
        $Data .= pack('n', $Value); // Data 10-11
        $Data .= "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00"; // Data 12-25
        $Data .= "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00"; // Data 26-41
        $Data .= chr(1) . $this->NodeId . "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00"; //Data 42-62
        $Data .= chr(0); // Data 63
        $Data .= chr(0) . chr(0) . chr(0); // Data 64-66
        $APIData = new \KLF200\APIData(\KLF200\APICommand::COMMAND_SEND_REQ, $Data);
        $ResultAPIData = $this->SendAPIData($APIData);
        if ($ResultAPIData === null) {
            return false;
        }
        return ord($ResultAPIData->Data[2]) == 1;
    }

    public function SetFunctionParameter2(int $Value)
    {
        /*
          Command               Data 1 – 2  Data 3              Data 4          Data 5
          GW_COMMAND_SEND_REQ   SessionID   CommandOriginator   PriorityLevel   ParameterActive
          Data 6    Data 7  Data 8 - 41                     Data 42         Data 43 – 62    Data 63
          FPI1      FPI2    FunctionalParameterValueArray   IndexArrayCount IndexArray      PriorityLevelLock
          Data 64   Data 65 Data 66
          PL_0_3    PL_4_7  LockTime
         */
        $Data = $this->NodeId . $this->GetSessionId(); //Data 1-2
        $Data .= chr(1) . chr(3) . chr(2); // Data 3-5
        $Data .= chr(0x40) . chr(0); // Data 6-7
        $Data .= "\xD4\x00"; // Data 8-9 -> ignore
        $Data .= "\x00\x00"; // Data 10-11
        $Data .= pack('n', $Value); // Data 12-13
        $Data .= "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00"; // Data 14-25
        $Data .= "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00"; // Data 26-41
        $Data .= chr(1) . $this->NodeId . "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00"; //Data 42-62
        $Data .= chr(0); // Data 63
        $Data .= chr(0) . chr(0) . chr(0); // Data 64-66
        $APIData = new \KLF200\APIData(\KLF200\APICommand::COMMAND_SEND_REQ, $Data);
        $ResultAPIData = $this->SendAPIData($APIData);
        if ($ResultAPIData === null) {
            return false;
        }
        return ord($ResultAPIData->Data[2]) == 1;
    }

    public function SetFunctionParameter3(int $Value)
    {
        /*
          Command               Data 1 – 2  Data 3              Data 4          Data 5
          GW_COMMAND_SEND_REQ   SessionID   CommandOriginator   PriorityLevel   ParameterActive
          Data 6    Data 7  Data 8 - 41                     Data 42         Data 43 – 62    Data 63
          FPI1      FPI2    FunctionalParameterValueArray   IndexArrayCount IndexArray      PriorityLevelLock
          Data 64   Data 65 Data 66
          PL_0_3    PL_4_7  LockTime
         */
        $Data = $this->NodeId . $this->GetSessionId(); //Data 1-2
        $Data .= chr(1) . chr(3) . chr(0); // Data 3-5
        $Data .= chr(0x20) . chr(0); // Data 6-7
        $Data .= "\xD4\x00"; // Data 8-9 -> ignore
        $Data .= "\x00\x00\x00\x00"; // Data 10-14
        $Data .= pack('n', $Value); // Data 14-15
        $Data .= "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00"; // Data 16-25
        $Data .= "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00"; // Data 26-41
        $Data .= chr(1) . $this->NodeId . "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00"; //Data 42-62
        $Data .= chr(0); // Data 63
        $Data .= chr(0) . chr(0) . chr(0); // Data 64-66
        $APIData = new \KLF200\APIData(\KLF200\APICommand::COMMAND_SEND_REQ, $Data);
        $ResultAPIData = $this->SendAPIData($APIData);
        if ($ResultAPIData === null) {
            return false;
        }
        return ord($ResultAPIData->Data[2]) == 1;
    }

    public function ReceiveData($JSONString)
    {
        $APIData = new \KLF200\APIData($JSONString);
        $this->SendDebug('Event', $APIData, 1);
        $this->ReceiveEvent($APIData);
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

    private function RegisterNodeVariables(int $NodeTypeSubType)
    {
        $this->NodeSubType = $NodeTypeSubType;
        switch ($NodeTypeSubType) {
            case 0x0040: //Interior Venetian Blind
                $this->RegisterVariableInteger('MAIN', $this->Translate('Position'), 'KLF200.Blind', 0);
                $this->RegisterVariableInteger('FP1', $this->Translate('Orientation'), 'KLF200.Slats', 0);
                $this->UnregisterVariable('FP2');
                $this->UnregisterVariable('FP3');
                break;
            case 0x0080: //Roller Shutter
            case 0x0082: //Roller Shutter With projection
                $this->RegisterVariableInteger('MAIN', $this->Translate('Position'), 'KLF200.RollerShutter', 0);
                $this->UnregisterVariable('FP1');
                $this->UnregisterVariable('FP2');
                $this->UnregisterVariable('FP3');
                break;
            case 0x0081: //Adjustable slats rolling shutter
                $this->RegisterVariableInteger('MAIN', $this->Translate('Position'), 'KLF200.RollerShutter', 0);
                $this->UnregisterVariable('FP1');
                $this->UnregisterVariable('FP2');
                $this->RegisterVariableInteger('FP3', $this->Translate('Orientation'), 'KLF200.Slats', 0);
                break;
            case 0x00C0: //Vertical Exterior Awning
                $this->RegisterVariableInteger('MAIN', $this->Translate('Position'), 'KLF200.RollerShutter', 0);
                $this->UnregisterVariable('FP1');
                $this->UnregisterVariable('FP2');
                $this->UnregisterVariable('FP3');
                break;
            case 0x0100: //Window opener
            case 0x0101: //Window opener with integrated rain sensor
                $this->RegisterVariableInteger('MAIN', $this->Translate('Position'), 'KLF200.Window', 0);
                $this->UnregisterVariable('FP1');
                $this->UnregisterVariable('FP2');
                $this->UnregisterVariable('FP3');
                break;
            case 0x0140: //Garage door opener
            case 0x017A: //Garage door opener
            case 0x0200: //Rolling Door Opener
                $this->RegisterVariableInteger('MAIN', $this->Translate('Position'), 'KLF200.Garage', 0);
                $this->UnregisterVariable('FP1');
                $this->UnregisterVariable('FP2');
                $this->UnregisterVariable('FP3');
                break;
            case 0x0180: //Light
                $this->RegisterVariableInteger('MAIN', $this->Translate('Intensity'), 'KLF200.Light.51200.Reversed', 0);
                $this->UnregisterVariable('FP1');
                $this->UnregisterVariable('FP2');
                $this->UnregisterVariable('FP3');
                break;
            case 0x01BA: //Light only supporting on/off
                $this->RegisterVariableBoolean('MAIN', $this->Translate('State'), 'KLF200.Light.Reversed', 0);
                $this->UnregisterVariable('FP1');
                $this->UnregisterVariable('FP2');
                $this->UnregisterVariable('FP3');
                break;
            case 0x01C0: //Gate opener
            case 0x01FA: //Gate opener
                $this->RegisterVariableInteger('MAIN', $this->Translate('Position'), 'KLF200.Intensity.51200', 0);
                $this->UnregisterVariable('FP1');
                $this->UnregisterVariable('FP2');
                $this->UnregisterVariable('FP3');
                break;
            case 0x0240: //Door lock
            case 0x0241: //Window lock
                $this->RegisterVariableBoolean('MAIN', $this->Translate('Lock'), 'KLF200.Lock', 0);
                $this->UnregisterVariable('FP1');
                $this->UnregisterVariable('FP2');
                $this->UnregisterVariable('FP3');
                break;
            case 0x0280: //Vertical Interior Blinds
                $this->RegisterVariableInteger('MAIN', $this->Translate('Position'), 'KLF200.Blind', 0);
                $this->UnregisterVariable('FP1');
                $this->UnregisterVariable('FP2');
                $this->UnregisterVariable('FP3');
                break;
            case 0x0340: //Dual Roller Shutter
                $this->RegisterVariableInteger('MAIN', $this->Translate('Dual Roller Shutter'), 'KLF200.RollerShutter', 0);
                $this->RegisterVariableInteger('FP1', $this->Translate('Upper position'), 'KLF200.RollerShutter', 0);
                $this->RegisterVariableInteger('FP2', $this->Translate('Lower position'), 'KLF200.RollerShutter', 0);
                $this->UnregisterVariable('FP3');
                break;
            case 0x03C0: //On/Off switch
                $this->RegisterVariableBoolean('MAIN', $this->Translate('Switch'), '~Switch', 0);
                $this->UnregisterVariable('FP1');
                $this->UnregisterVariable('FP2');
                $this->UnregisterVariable('FP3');
                break;
            case 0x0400: //Horizontal awning
            case 0x04C0: //Curtain track
            case 0x0600: //Swinging Shutters
            case 0x0601: //Swinging Shutter with independent handling of the leaves
                $this->RegisterVariableInteger('MAIN', $this->Translate('Position'), 'KLF200.Intensity.51200', 0);
                $this->UnregisterVariable('FP1');
                $this->UnregisterVariable('FP2');
                $this->UnregisterVariable('FP3');
                break;
            case 0x0440: //Exterior Venetian blind
                $this->RegisterVariableInteger('MAIN', $this->Translate('Position'), 'KLF200.Blind', 0);
                $this->UnregisterVariable('FP1');
                $this->UnregisterVariable('FP2');
                $this->RegisterVariableInteger('FP3', $this->Translate('Orientation'), 'KLF200.Slats', 0);
                break;
            case 0x0480: //Louver blind
                $this->RegisterVariableInteger('MAIN', $this->Translate('Position'), 'KLF200.Intensity.51200', 0);
                $this->UnregisterVariable('FP1');
                $this->UnregisterVariable('FP2');
                $this->RegisterVariableInteger('FP3', $this->Translate('Orientation'), 'KLF200.Slats', 0);
                break;
            case 0x0500: //Ventilation point
            case 0x0501: //Air inlet
            case 0x0502: //Air transfer
            case 0x0503: //Air outlet
                $this->RegisterVariableInteger('MAIN', $this->Translate('Closed'), 'KLF200.Intensity.51200', 0);
                $this->UnregisterVariable('FP1');
                $this->UnregisterVariable('FP2');
                $this->UnregisterVariable('FP3');
                break;
            case 0x0540: //Exterior heating
            case 0x057A: //Exterior heating
                $this->RegisterVariableInteger('MAIN', $this->Translate('Closed'), 'KLF200.Intensity.51200', 0);
                $this->UnregisterVariable('FP1');
                $this->UnregisterVariable('FP2');
                $this->UnregisterVariable('FP3');
                break;
            case 0x0300: //Beacon
            case 0x0380: //Heating Temperature Interface
            case 0x0580: //Heat pump
            case 0x05C0: //Intrusion alarm
            default:
                $this->UnregisterVariable('MAIN');
                $this->UnregisterVariable('FP1');
                $this->UnregisterVariable('FP2');
                $this->UnregisterVariable('FP3');
                break;
        }
        if (@$this->GetIDForIdent('MAIN') > 0) {
            $this->EnableAction('MAIN');
        }
        if (@$this->GetIDForIdent('FP1') > 0) {
            $this->EnableAction('FP1');
        }
        if (@$this->GetIDForIdent('FP2') > 0) {
            $this->EnableAction('FP2');
        }
        if (@$this->GetIDForIdent('FP3') > 0) {
            $this->EnableAction('FP3');
        }
    }

    private function SetValues(int $CurrentPosition, int $FP1CurrentPosition, int $FP2CurrentPosition, int $FP3CurrentPosition)
    {
        // nur absolute Werte in Variablen schreiben
        $Main = @$this->GetIDForIdent('MAIN');
        if (($Main > 0) && ($CurrentPosition <= 0xC800)) {
            if (IPS_GetVariable($Main)['VariableType'] == VARIABLETYPE_BOOLEAN) {
                $CurrentPosition = ($CurrentPosition == 0xC800);
            }
            $this->SetValue('MAIN', $CurrentPosition);
        }
        $FP1 = @$this->GetIDForIdent('FP1');
        if (($FP1 > 0) && ($FP1CurrentPosition <= 0xC800)) {
            if (IPS_GetVariable($FP1)['VariableType'] == VARIABLETYPE_BOOLEAN) {
                $FP1CurrentPosition = ($FP1CurrentPosition == 0xC800);
            }
            $this->SetValue('FP1', $FP1CurrentPosition);
        }
        $FP2 = @$this->GetIDForIdent('FP2');
        if (($FP2 > 0) && ($FP2CurrentPosition <= 0xC800)) {
            if (IPS_GetVariable($FP2)['VariableType'] == VARIABLETYPE_BOOLEAN) {
                $FP2CurrentPosition = ($FP2CurrentPosition == 0xC800);
            }
            $this->SetValue('FP2', $FP2CurrentPosition);
        }
        $FP3 = @$this->GetIDForIdent('FP3');
        if (($FP3 > 0) && ($FP3CurrentPosition <= 0xC800)) {
            if (IPS_GetVariable($FP3)['VariableType'] == VARIABLETYPE_BOOLEAN) {
                $FP3CurrentPosition = ($FP3CurrentPosition == 0xC800);
            }
            $this->SetValue('FP3', $FP3CurrentPosition);
        }
    }

    private function ReceiveEvent(\KLF200\APIData $APIData)
    {
        switch ($APIData->Command) {
            case \KLF200\APICommand::GET_NODE_INFORMATION_NTF:
                $NodeID = ord($APIData->Data[0]);
                /* Data 1 Data 2 - 3 Data 4    Data 5 - 68 Data 69
                  NodeID  Order      Placement Name        Velocity
                  Data 70 - 71    Data 72      Data 73     Data 74       Data 75   Data 76
                  NodeTypeSubType ProductGroup ProductType NodeVariation PowerMode BuildNumber
                  Data 77 - 84 Data 85 Data 86 - 87    Data 88 - 89 Data 90 - 91       Data 92 - 93
                  SerialNumber State   CurrentPosition Target       FP1CurrentPosition FP2CurrentPosition
                  Data 94 - 95       Data 96 - 97       Data 98 - 99  Data 100 - 103 Data 104   Data 105 - 125
                  FP3CurrentPosition FP4CurrentPosition RemainingTime TimeStamp      NbrOfAlias AliasArray
                 */

                $Name = trim(substr($APIData->Data, 4, 64));
                $NodeTypeSubType = unpack('n', substr($APIData->Data, 69, 2))[1];
                $this->SendDebug('NodeID', $NodeID, 0);
                $this->SendDebug('Name', $Name, 0);
                $this->SendDebug('NodeTypeSubType', sprintf('%04X', $NodeTypeSubType), 0);
                $this->SendDebug('NodeTypeSubType', \KLF200\Node::$SubType[$NodeTypeSubType], 0);
                $this->SendDebug('NodeType', ($NodeTypeSubType >> 6), 0);
                $this->SendDebug('SubType', ($NodeTypeSubType & 0x003F), 0);
                /*
                  $this->SendDebug('ProductGroup', ord($APIData->Data[71]), 0);
                  $this->SendDebug('ProductType', ord($APIData->Data[72]), 0);
                  $this->SendDebug('NodeVariation', ord($APIData->Data[73]), 0);
                  $this->SendDebug('PowerMode', ord($APIData->Data[74]), 0);
                  $this->SendDebug('BuildNumber', ord($APIData->Data[75]), 0);
                  $this->SendDebug('SerialNumber', substr($APIData->Data, 76, 8), 1);
                 */
                $State = ord($APIData->Data[84]);
                $this->SendDebug('State', \KLF200\State::ToString($State), 0);
                $CurrentPosition = unpack('n', substr($APIData->Data, 85, 2))[1];
                $this->SendDebug('CurrentPosition', sprintf('%04X', $CurrentPosition), 0);
                /* $Target = unpack('n', substr($APIData->Data, 87, 2))[1];
                  $this->SendDebug('Target', sprintf('%04X', $Target), 0);
                 */
                $FP1CurrentPosition = unpack('n', substr($APIData->Data, 89, 2))[1];
                $this->SendDebug('FP1CurrentPosition', sprintf('%04X', $FP1CurrentPosition), 0);
                $FP2CurrentPosition = unpack('n', substr($APIData->Data, 91, 2))[1];
                $this->SendDebug('FP2CurrentPosition', sprintf('%04X', $FP2CurrentPosition), 0);
                $FP3CurrentPosition = unpack('n', substr($APIData->Data, 93, 2))[1];
                $this->SendDebug('FP3CurrentPosition', sprintf('%04X', $FP3CurrentPosition), 0);
                /*
                  $FP4CurrentPosition = unpack('n', substr($APIData->Data, 95, 2))[1];
                  $this->SendDebug('FP4CurrentPosition', sprintf('%04X', $FP4CurrentPosition), 0);
                  $RemainingTime = unpack('n', substr($APIData->Data, 97, 2))[1];
                  $this->SendDebug('RemainingTime', $RemainingTime, 0);
                  $TimeStamp = unpack('N', substr($APIData->Data, 99, 4))[1];
                  $this->SendDebug('TimeStamp', $TimeStamp, 0);
                  $this->SendDebug('TimeStamp', strftime('%H:%M:%S %d.%m.%Y', $TimeStamp), 0);
                 */
                if ($NodeTypeSubType != $this->NodeSubType) {
                    $this->WriteAttributeInteger('NodeSubType', $NodeTypeSubType);
                    $this->SetSummary(sprintf('%04X', $NodeTypeSubType));
                    $this->RegisterNodeVariables($NodeTypeSubType);
                }
                $this->SetValues($CurrentPosition, $FP1CurrentPosition, $FP2CurrentPosition, $FP3CurrentPosition);
                break;
                /* case \KLF200\APICommand::NODE_INFORMATION_CHANGED_NTF:
                  break; */
            case \KLF200\APICommand::NODE_STATE_POSITION_CHANGED_NTF:
                /*
                  Data 1 Data 2 Data 3 - 4      Data 5 - 6
                  NodeID State  CurrentPosition Target
                  Data 7 - 8         Data 9 - 10        Data 11 -12        Data 13 - 14       Data 15 - 16
                  FP1CurrentPosition FP2CurrentPosition FP3CurrentPosition FP4CurrentPosition RemainingTime
                  Data 17 - 20
                  TimeStamp
                 */
                $State = ord($APIData->Data[1]);
                $this->SendDebug('State', \KLF200\State::ToString($State), 0);
                $CurrentPosition = unpack('n', substr($APIData->Data, 2, 2))[1];
                $this->SendDebug('CurrentPosition', sprintf('%04X', $CurrentPosition), 0);
                $Target = unpack('n', substr($APIData->Data, 4, 2))[1];
                $this->SendDebug('Target', sprintf('%04X', $Target), 0);
                $FP1CurrentPosition = unpack('n', substr($APIData->Data, 6, 2))[1];
                $this->SendDebug('FP1CurrentPosition', sprintf('%04X', $FP1CurrentPosition), 0);
                $FP2CurrentPosition = unpack('n', substr($APIData->Data, 8, 2))[1];
                $this->SendDebug('FP2CurrentPosition', sprintf('%04X', $FP2CurrentPosition), 0);
                $FP3CurrentPosition = unpack('n', substr($APIData->Data, 10, 2))[1];
                /*
                  $this->SendDebug('FP3CurrentPosition', sprintf('%04X', $FP3CurrentPosition), 0);
                  $FP4CurrentPosition = unpack('n', substr($APIData->Data, 12, 2))[1];
                  $this->SendDebug('FP4CurrentPosition', sprintf('%04X', $FP4CurrentPosition), 0);
                 */
                $RemainingTime = unpack('n', substr($APIData->Data, 14, 2))[1];
                $this->SendDebug('RemainingTime', $RemainingTime, 0);
                $TimeStamp = unpack('N', substr($APIData->Data, 16, 4))[1];
                $this->SendDebug('TimeStamp', $TimeStamp, 0);
                $this->SendDebug('TimeStamp', strftime('%H:%M:%S %d.%m.%Y', $TimeStamp), 0);
                if ($State == \KLF200\State::DONE) {
                    // Wert $CurrentPosition umrechnen und setzen
                    $this->SetValues($CurrentPosition, $FP1CurrentPosition, $FP2CurrentPosition, $FP3CurrentPosition);
                }
                break;
            case \KLF200\APICommand::COMMAND_RUN_STATUS_NTF:
                // 00 06 01 00 00 FF FF 01 02 0E 00 00 00
                /*
                  Command                   Data 1 - 2  Data 3      Data 4
                  GW_COMMAND_RUN_STATUS_NTF SessionID   StatusID    Index
                  Data 5        Data 6 – 7
                  NodeParameter ParameterValue
                  Data 8    Data 9      Data 10 - 13
                  RunStatus StatusReply InformationCode
                 */
                $NodeParameter = ord($APIData->Data[4]);
                $this->SendDebug('NodeParameter', $NodeParameter, 0);
                $ParameterValue = unpack('n', substr($APIData->Data, 5, 2))[1];
                $this->SendDebug('ParameterValue', sprintf('%04X', $ParameterValue), 0);
                $RunStatus = ord($APIData->Data[7]);
                $this->SendDebug('RunStatus', \KLF200\RunStatus::ToString($RunStatus), 0);
                $StatusReply = ord($APIData->Data[8]);
                $this->SendDebug('StatusReply', \KLF200\StatusReply::ToString($StatusReply), 0);
                if ($RunStatus == \KLF200\RunStatus::EXECUTION_FAILED) {
                    trigger_error($this->Translate(\KLF200\RunStatus::ToString($RunStatus)), E_USER_NOTICE);
                    return;
                }

                break;
            case \KLF200\APICommand::COMMAND_REMAINING_TIME_NTF:
                break;
            case \KLF200\APICommand::SESSION_FINISHED_NTF:
                break;
            case \KLF200\APICommand::STATUS_REQUEST_NTF:
                //00 00 01 00 01 02 FF 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00
                //01 05 01 01 00 01 01

                /*
                  Command                Data 1 – 2 Data 3   Data 4    Data 5    Data 6
                  GW_STATUS_REQUEST_NTF  SessionID  StatusID NodeIndex RunStatus StatusReply
                  Data 7
                  StatusType
                 *      0 = “Target Position” or
                 *      1 = “Current Position” or
                 *      2 = “Remaining Time”
                 *      01          00 C8 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00
                 *      Data 8      Data 9 - 59
                 *      StatusCount ParameterData
                 *
                  Data 7
                  StatusType
                 *      3 = “Main Info”
                 *      Data 8 - 9      Data 10 - 11    Data 12 - 13
                 *      TargetPosition  CurrentPosition RemainingTime
                 *      Data 14 - 17                Data 18
                 *      LastMasterExecutionAddress  LastCommandOriginator
                 */
                $StatusID = ord($APIData->Data[2]);
                $this->SendDebug('StatusID', $StatusID, 0);
                $NodeIndex = ord($APIData->Data[3]);
                $this->SendDebug('NodeIndex', $NodeIndex, 0);
                $RunStatus = ord($APIData->Data[4]);
                $this->SendDebug('RunStatus', \KLF200\RunStatus::ToString($RunStatus), 0);
                $StatusReply = ord($APIData->Data[5]);
                $this->SendDebug('StatusReply', \KLF200\StatusReply::ToString($StatusReply), 0);
                $StatusType = ord($APIData->Data[6]);
                $this->SendDebug('StatusType', $StatusType, 0);
                if ($StatusType == 0xFF) {
                    $this->SendDebug('Error', \KLF200\StatusReply::ToString($StatusReply), 0);
                    trigger_error($this->Translate(\KLF200\StatusReply::ToString($StatusReply)), E_USER_NOTICE);
                    return;
                }
                if ($StatusType == 0x01) {
                    $ParameterCount = ord($APIData->Data[7]);
                    $this->SendDebug('ParameterCount', $ParameterCount, 0);
                    $ParameterData = substr($APIData->Data, 8);
                    $Data = [
                        0 => 0xF7FF,
                        1 => 0xF7FF,
                        2 => 0xF7FF,
                        3 => 0xF7FF
                    ];
                    for ($index = 0; $index < $ParameterCount; $index++) {
                        $Data[ord($ParameterData[$index * 3])] = unpack('n', substr($ParameterData, ($index * 3) + 1, 2))[1];
                    }
                    $this->SetValues($Data[0], $Data[1], $Data[2], $Data[3]);
                }
                break;
            case \KLF200\APICommand::WINK_SEND_NTF:
                break;
            case \KLF200\APICommand::MODE_SEND_NTF:
                break;
        }
    }

    private function GetSessionId()
    {
        $SessionId = ($this->SessionId + 1) & 0xff;
        $this->SessionId = $SessionId;
        return chr($SessionId);
    }

    private function SendAPIData(\KLF200\APIData $APIData)
    {
        if ($this->NodeId == chr(-1)) {
            return null;
        }
        $this->SendDebug('ForwardData', $APIData, 1);

        try {
            if (!$this->HasActiveParent()) {
                throw new Exception($this->Translate('Instance has no active parent.'), E_USER_NOTICE);
            }
            /** @var \KLF200\APIData $ResponseAPIData */
            $ret = @$this->SendDataToParent($APIData->ToJSON('{7B0F87CC-0408-4283-8E0E-2D48141E42E8}'));
            $ResponseAPIData = @unserialize($ret);
            $this->SendDebug('Response', $ResponseAPIData, 1);
            if ($ResponseAPIData->isError()) {
                trigger_error($this->Translate($ResponseAPIData->ErrorToString()), E_USER_NOTICE);
                return null;
            }
            return $ResponseAPIData;
        } catch (Exception $exc) {
            $this->SendDebug('Error', $exc->getMessage(), 0);
            return null;
        }
    }
}
