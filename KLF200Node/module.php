<?php

declare(strict_types=1);
require_once __DIR__ . '/../libs/KLF200Class.php';  // diverse Klassen
eval('declare(strict_types=1);namespace KLF200Node {?>' . file_get_contents(__DIR__ . '/../libs/helper/BufferHelper.php') . '}');
eval('declare(strict_types=1);namespace KLF200Node {?>' . file_get_contents(__DIR__ . '/../libs/helper/SemaphoreHelper.php') . '}');
eval('declare(strict_types=1);namespace KLF200Node {?>' . file_get_contents(__DIR__ . '/../libs/helper/DebugHelper.php') . '}');
eval('declare(strict_types=1);namespace KLF200Node {?>' . file_get_contents(__DIR__ . '/../libs/helper/VariableHelper.php') . '}');
eval('declare(strict_types=1);namespace KLF200Node {?>' . file_get_contents(__DIR__ . '/../libs/helper/VariableProfileHelper.php') . '}');

/**
 * KLF200Node Klasse implementiert ein Gerät
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
 * @method void RegisterProfileIntegerEx(string $Name, string $Icon, string $Prefix, string $Suffix, array $Associations, int $MaxValue = -1, int $StepSize = 0)
 * @method void RegisterProfileInteger(string $Name, string $Icon, string $Prefix, string $Suffix, int $MinValue, int $MaxValue, int $StepSize)
 * @method void RegisterProfileBoolean(string $Name, string $Icon, string $Prefix, string $Suffix)
 * @method void RegisterProfileBooleanEx($Name, $Icon, $Prefix, $Suffix, $Associations)
 * @method void  UnregisterProfile(string $Name)
 *
 * @property char $NodeId
 * @property int $SessionId
 * @property int $NodeSubType
 * @property int[] $SessionRunStatus
 */
class KLF200Node extends IPSModule
{
    use \KLF200Node\Semaphore,
        \KLF200Node\BufferHelper,
        \KLF200Node\VariableHelper,
        \KLF200Node\VariableProfileHelper,
        \KLF200Node\DebugHelper {
            \KLF200Node\DebugHelper::SendDebug as SendDebugTrait;
        }

    /**
     * Interne Funktion des SDK.
     */
    public function Create()
    {
        parent::Create();
        $this->ConnectParent(\KLF200\GUID::Gateway);
        $this->RegisterPropertyInteger(\KLF200\Node\Property::NodeId, -1);
        $this->RegisterPropertyBoolean(\KLF200\Node\Property::WaitForFinishSession, false);
        $this->RegisterPropertyBoolean(\KLF200\Node\Property::AutoRename, false);
        $this->RegisterAttributeInteger(\KLF200\Node\Attribute::NodeSubType, -1);
        $this->SessionId = 1;
        $this->NodeSubType = -1;
        $this->SessionRunStatus = [];
    }

    /**
     * Interne Funktion des SDK.
     */
    public function ApplyChanges()
    {
        parent::ApplyChanges();
        $this->SessionId = 1;
        $this->SessionRunStatus = [];
        $NodeId = $this->ReadPropertyInteger(\KLF200\Node\Property::NodeId);
        $this->NodeId = chr($NodeId);
        if (($NodeId < 0) || ($NodeId > 255)) {
            $Line = 'NOTHING';
        } else {
            //$NodeId = preg_quote(substr(json_encode(utf8_encode(chr($this->ReadPropertyInteger(\KLF200\Node\Property::NodeId)))), 0, -1));
            foreach (array_keys(\KLF200\APICommand::$EventsToNodeId) as $APICommand) {
                $Lines[] = '.*"Command":' . $APICommand . ',"NodeID":' . $NodeId . ',.*';
            }
            $Line = implode('|', $Lines);
        }
        $this->SetReceiveDataFilter('(' . $Line . ')');
        $this->SendDebug('FILTER', $Line, 0);
        $this->NodeSubType = $this->ReadAttributeInteger(\KLF200\Node\Attribute::NodeSubType);
        $this->SetSummary(sprintf('%04X', $this->NodeSubType));
        $this->RegisterProfileIntegerEx(
            'KLF200.StatusOwner',
            '',
            '',
            '',
            [
                [\KLF200\StatusID::STATUS_LOCAL_USER,       'local activation', '', -1],
                [\KLF200\StatusID::STATUS_USER,             'Symcon', '', -1],
                [\KLF200\StatusID::STATUS_RAIN,             'rain sensor activation', '', -1],
                [\KLF200\StatusID::STATUS_TIMER,            'timer activation', '', -1],
                [\KLF200\StatusID::STATUS_UPS,              'UPS activation', '', -1],
                [\KLF200\StatusID::STATUS_PROGRAM,          'program activation', '', -1],
                [\KLF200\StatusID::STATUS_WIND,             'wind sensor activation', '', -1],
                [\KLF200\StatusID::STATUS_MYSELF,           'actuator generated activation', '', -1],
                [\KLF200\StatusID::STATUS_AUTOMATIC_CYCLE,  'automatic cycle activation', '', -1],
                [\KLF200\StatusID::STATUS_EMERGENCY,        'emergency or security activation', '', -1],
                [\KLF200\StatusID::STATUS_UNKNOWN,          'unknown source', '', -1]
            ]
        );

        $this->RegisterProfileInteger('KLF200.Intensity.51200', '', '', ' %', 0, 0xC800, 1);
        $this->RegisterProfileInteger('KLF200.RollerShutter', 'Jalousie', '', ' %', 0, 0xC800, 1);
        $this->RegisterProfileInteger('KLF200.Slats', 'Speedo', '', ' %', 0, 0xC800, 1);
        $this->RegisterProfileInteger('KLF200.Blind', 'Raffstore', '', ' %', 0, 0xC800, 1);
        $this->RegisterProfileInteger('KLF200.Window', 'Window', '', ' %', 0, 0xC800, 1);
        $this->RegisterProfileInteger('KLF200.Heating.Reversed', 'Temperature', '', ' %', 0, 0xC800, 1);
        $this->RegisterProfileInteger('KLF200.Garage', 'Garage', '', ' %', 0, 0xC800, 1);
        $this->RegisterProfileInteger('KLF200.Light.51200.Reversed', 'Light', '', ' %', 0, 0xC800, 1);
        $this->UnregisterProfile('KLF200.Light.Reversed');
        $this->UnregisterProfile('KLF200.Lock');
        $this->RegisterProfileBooleanEx('KLF200.RunStatus', 'Gear', '', '', [
            [false, 'stopped', '', -1],
            [true, 'running', '', 0x0000ff],

        ]);
        $this->RegisterVariableInteger('LastSeen', $this->Translate('last seen'), '~UnixTimestamp', 0);
        $this->RegisterVariableInteger('LastActivation', $this->Translate('last activation'), 'KLF200.StatusOwner', 0);
        $this->RegisterVariableString('ErrorState', $this->Translate('last error'), '', 0);
        $this->RegisterVariableBoolean('RunStatus', $this->Translate('run status'), 'KLF200.RunStatus', 0);
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
        return $this->OrientationSet(0x0000);
    }

    public function OrientationDown()
    {
        return $this->OrientationSet(0xC800);
    }

    public function OrientationStop()
    {
        return $this->OrientationSet(0xD200);
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
            $Value = $Value ? 0x0000 : 0xC800;
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
        $ResultStatus = ord($ResultAPIData->Data[0]);
        if ($ResultStatus == \KLF200\Status::REQUEST_ACCEPTED) {
            return true;
        }
        trigger_error($this->Translate(\KLF200\Status::ToString($ResultStatus * 2)), E_USER_NOTICE);
        return false;
    }

    public function RequestStatus()
    {
        /*
          Command               Data 1 – 2 Data 3          Data 4 – 23      Data 24
          GW_STATUS_REQUEST_REQ SessionID  IndexArrayCount IndexArrayOfInt  StatusType
          Data 25               Data 26
          FPI1 (BitFlags)       FPI2 (BitFlags)
         */
        $Data = $this->NodeId . $this->GetSessionId();
        $SessionID = unpack('n', $Data)[1];
        $Data .= chr(1) . $this->NodeId . "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00";
        $Data .= chr(\KLF200\StatusType::Current_position) . chr(0b11100000) . chr(0);
        $APIData = new \KLF200\APIData(\KLF200\APICommand::STATUS_REQUEST_REQ, $Data);
        $Result = $this->SendAPIData($APIData, $SessionID);
        if ($Result !== true) {
            return false;
        }
        return true;
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
        $SessionID = unpack('n', $Data)[1];
        $Data .= chr(1) . chr(3) . chr(0); // Data 3-5
        $Data .= chr(0) . chr(0); // Data 6-7
        $Data .= pack('n', $Value); // Data 8-9
        $Data .= "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00"; // Data 10-25
        $Data .= "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00"; // Data 26-41
        $Data .= chr(1) . $this->NodeId . "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00"; //Data 42-62
        $Data .= chr(0); // Data 63
        $Data .= chr(0) . chr(0) . chr(0); // Data 64-66
        $APIData = new \KLF200\APIData(\KLF200\APICommand::COMMAND_SEND_REQ, $Data);
        $Result = $this->SendAPIData($APIData, $SessionID);
        if ($Result !== true) {
            return false;
        }
        return true;
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
        $SessionID = unpack('n', $Data)[1];
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
        $Result = $this->SendAPIData($APIData, $SessionID);
        if ($Result !== true) {
            return false;
        }
        return true;
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
        $SessionID = unpack('n', $Data)[1];
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
        $Result = $this->SendAPIData($APIData, $SessionID);
        if ($Result !== true) {
            return false;
        }
        return true;
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
        $SessionID = unpack('n', $Data)[1];
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
        $Result = $this->SendAPIData($APIData, $SessionID);
        if ($Result !== true) {
            return false;
        }
        return true;
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
                $this->RegisterVariableBoolean('MAIN', $this->Translate('State'), '~Switch', 0);
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
                $this->RegisterVariableBoolean('MAIN', $this->Translate('Lock'), '~Lock', 0);
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

    private function SetParameterValue(int $NodeParameter, int $ParameterValue)
    {
        $Ident = ($NodeParameter > 0) ? 'FP' . $NodeParameter : 'MAIN';
        $this->SendDebug($Ident, sprintf('%04X', $ParameterValue), 0);
        // nur absolute Werte in Variablen schreiben
        $VarId = @$this->GetIDForIdent($Ident);
        if (($VarId > 0) && ($ParameterValue <= 0xC800)) {
            if (IPS_GetVariable($VarId)['VariableType'] == VARIABLETYPE_BOOLEAN) {
                $ParameterValue = !($ParameterValue == 0xC800);
            }

            $this->SetValue($Ident, $ParameterValue);
        }
    }
    private function SetValues(int $CurrentPosition, int $FP1CurrentPosition, int $FP2CurrentPosition, int $FP3CurrentPosition)
    {
        // nur absolute Werte in Variablen schreiben
        $Main = @$this->GetIDForIdent('MAIN');
        if (($Main > 0) && ($CurrentPosition <= 0xC800)) {
            if (IPS_GetVariable($Main)['VariableType'] == VARIABLETYPE_BOOLEAN) {
                $CurrentPosition = !($CurrentPosition == 0xC800);
            }
            $this->SetValue('MAIN', $CurrentPosition);
        }
        $FP1 = @$this->GetIDForIdent('FP1');
        if (($FP1 > 0) && ($FP1CurrentPosition <= 0xC800)) {
            if (IPS_GetVariable($FP1)['VariableType'] == VARIABLETYPE_BOOLEAN) {
                $FP1CurrentPosition = !($FP1CurrentPosition == 0xC800);
            }
            $this->SetValue('FP1', $FP1CurrentPosition);
        }
        $FP2 = @$this->GetIDForIdent('FP2');
        if (($FP2 > 0) && ($FP2CurrentPosition <= 0xC800)) {
            if (IPS_GetVariable($FP2)['VariableType'] == VARIABLETYPE_BOOLEAN) {
                $FP2CurrentPosition = !($FP2CurrentPosition == 0xC800);
            }
            $this->SetValue('FP2', $FP2CurrentPosition);
        }
        $FP3 = @$this->GetIDForIdent('FP3');
        if (($FP3 > 0) && ($FP3CurrentPosition <= 0xC800)) {
            if (IPS_GetVariable($FP3)['VariableType'] == VARIABLETYPE_BOOLEAN) {
                $FP3CurrentPosition = !($FP3CurrentPosition == 0xC800);
            }
            $this->SetValue('FP3', $FP3CurrentPosition);
        }
    }

    private function ReceiveEvent(\KLF200\APIData $APIData)
    {
        switch ($APIData->Command) {
            case \KLF200\APICommand::GET_ALL_NODES_INFORMATION_NTF:
            case \KLF200\APICommand::GET_NODE_INFORMATION_NTF:
                /* Data 1 Data 2 - 3 Data 4    Data 5 - 68 Data 69
                  NodeID  Order      Placement Name        Velocity
                  Data 70 - 71    Data 72      Data 73     Data 74       Data 75   Data 76
                  NodeTypeSubType ProductGroup ProductType NodeVariation PowerMode BuildNumber
                  Data 77 - 84 Data 85 Data 86 - 87    Data 88 - 89 Data 90 - 91       Data 92 - 93
                  SerialNumber State   CurrentPosition Target       FP1CurrentPosition FP2CurrentPosition
                  Data 94 - 95       Data 96 - 97       Data 98 - 99  Data 100 - 103 Data 104   Data 105 - 125
                  FP3CurrentPosition FP4CurrentPosition RemainingTime TimeStamp      NbrOfAlias AliasArray
                 */
                $NodeID = ord($APIData->Data[0]);
                $Name = trim(substr($APIData->Data, 4, 64));
                $NodeTypeSubType = unpack('n', substr($APIData->Data, 69, 2))[1];
                $this->SendDebug('NodeID (' . $APIData->NodeID . ')', $NodeID, 0);
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
                 */
                $TimeStamp = unpack('N', substr($APIData->Data, 99, 4))[1];
                $this->SendDebug('TimeStamp', $TimeStamp, 0);
                $this->SetValue('LastSeen', $TimeStamp);
                if ($NodeTypeSubType != $this->NodeSubType) {
                    $this->WriteAttributeInteger(\KLF200\Node\Attribute::NodeSubType, $NodeTypeSubType);
                    $this->SetSummary(sprintf('%04X', $NodeTypeSubType));
                    $this->RegisterNodeVariables($NodeTypeSubType);
                }
                $this->SetValues($CurrentPosition, $FP1CurrentPosition, $FP2CurrentPosition, $FP3CurrentPosition);
                $this->AutoRename($Name);
                return;
            case \KLF200\APICommand::NODE_INFORMATION_CHANGED_NTF:
                $Name = trim(substr($APIData->Data, 4, 64));
                $this->SendDebug('Name', $Name, 0);
                $this->AutoRename($Name);
                return;
            case \KLF200\APICommand::NODE_STATE_POSITION_CHANGED_NTF:
                /*
                  Data 1 Data 2 Data 3 - 4      Data 5 - 6
                  NodeID State  CurrentPosition Target
                  Data 7 - 8         Data 9 - 10        Data 11 -12        Data 13 - 14       Data 15 - 16
                  FP1CurrentPosition FP2CurrentPosition FP3CurrentPosition FP4CurrentPosition RemainingTime
                  Data 17 - 20
                  TimeStamp
                 */
                $NodeID = ord($APIData->Data[0]);
                $this->SendDebug('NodeID (' . $APIData->NodeID . ')', $NodeID, 0);
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
                  $RemainingTime = unpack('n', substr($APIData->Data, 14, 2))[1];
                  $this->SendDebug('RemainingTime', $RemainingTime, 0);
                 */
                if (substr($APIData->Data, 18, 2) == "\x00\x00") { //Timestamp Bug
                    // fixit
                    $TimeStamp = (time() & 0xffff0000) ^ (unpack('n', substr($APIData->Data, 16, 2))[1]);
                } else {
                    $TimeStamp = unpack('N', substr($APIData->Data, 16, 4))[1];
                }
                $this->SendDebug('TimeStamp', $TimeStamp, 0);
                $this->SetValue('LastSeen', $TimeStamp);
                if ($State == \KLF200\State::DONE) {
                    // Wert $CurrentPosition umrechnen und setzen
                    $this->SetValues($CurrentPosition, $FP1CurrentPosition, $FP2CurrentPosition, $FP3CurrentPosition);
                }
                return;
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

                $SessionID = unpack('n', substr($APIData->Data, 0, 2))[1];
                $this->SendDebug('SessionID', sprintf('%04X', $SessionID), 0);
                $StatusID = ord($APIData->Data[2]);
                $this->SendDebug('StatusID', $StatusID, 0);
                $this->SendDebug('StatusID', \KLF200\StatusID::ToString($StatusID), 0);
                $this->SetValue('LastActivation', $StatusID);
                $NodeParameter = ord($APIData->Data[4]);
                $this->SendDebug('NodeParameter', $NodeParameter, 0);
                $ParameterValue = unpack('n', substr($APIData->Data, 5, 2))[1];
                $this->SendDebug('ParameterValue', sprintf('%04X', $ParameterValue), 0);
                $RunStatus = ord($APIData->Data[7]);
                $this->SendDebug('RunStatus', \KLF200\RunStatus::ToString($RunStatus), 0);
                $this->SetValue('RunStatus', $RunStatus == \KLF200\RunStatus::EXECUTION_ACTIVE);
                $StatusReply = ord($APIData->Data[8]);
                $this->SendDebug('StatusReply', \KLF200\StatusReply::ToString($StatusReply), 0);
                $this->SetValue('ErrorState', $this->Translate(\KLF200\StatusReply::ToString($StatusReply)));
                if ($RunStatus == \KLF200\RunStatus::EXECUTION_COMPLETED) {
                    $this->SetParameterValue($NodeParameter, $ParameterValue);
                }
                if (!$this->SessionQueueUpdate($SessionID, $RunStatus, $StatusReply)) {
                    if ($RunStatus == \KLF200\RunStatus::EXECUTION_FAILED) {
                        $this->SendDebug('Error', \KLF200\StatusReply::ToString($StatusReply), 0);
                        trigger_error($this->Translate(\KLF200\StatusReply::ToString($StatusReply)), E_USER_NOTICE);
                    }
                }
                return;
            case \KLF200\APICommand::COMMAND_REMAINING_TIME_NTF:
                return;
            case \KLF200\APICommand::STATUS_REQUEST_NTF:
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

                $SessionID = unpack('n', substr($APIData->Data, 0, 2))[1];
                $this->SendDebug('SessionID', sprintf('%04X', $SessionID), 0);
                $StatusID = ord($APIData->Data[2]);
                $this->SendDebug('StatusID', $StatusID, 0);
                $this->SendDebug('StatusID', \KLF200\StatusID::ToString($StatusID), 0);
                $this->SetValue('LastActivation', $StatusID);
                $NodeIndex = ord($APIData->Data[3]);
                $this->SendDebug('NodeIndex', $NodeIndex, 0);
                $RunStatus = ord($APIData->Data[4]);
                $this->SendDebug('RunStatus', \KLF200\RunStatus::ToString($RunStatus), 0);
                $this->SetValue('RunStatus', $RunStatus == \KLF200\RunStatus::EXECUTION_ACTIVE);
                $StatusReply = ord($APIData->Data[5]);
                $this->SendDebug('StatusReply', \KLF200\StatusReply::ToString($StatusReply), 0);
                $this->SetValue('ErrorState', $this->Translate(\KLF200\StatusReply::ToString($StatusReply)));
                $StatusType = ord($APIData->Data[6]);
                $this->SendDebug('StatusType', \KLF200\StatusType::ToString($StatusType), 0);
                if ($RunStatus == \KLF200\RunStatus::EXECUTION_COMPLETED) {
                    switch ($StatusType) {
                        case \KLF200\StatusType::Target_position:
                            break;
                        case \KLF200\StatusType::Current_position:
                            $ParameterCount = ord($APIData->Data[7]);
                            $this->SendDebug('ParameterCount', $ParameterCount, 0);
                            $ParameterData = substr($APIData->Data, 8);
                            for ($index = 0; $index < $ParameterCount; $index++) {
                                $NodeParameter = ord($ParameterData[$index * 3]);
                                $ParameterValue = unpack('n', substr($ParameterData, ($index * 3) + 1, 2))[1];
                                $this->SetParameterValue($NodeParameter, $ParameterValue);
                            }
                            break;
                        case \KLF200\StatusType::Remaining_time:
                            break;
                        case \KLF200\StatusType::Main_info:
                            break;
                    }
                }
                if (!$this->SessionQueueUpdate($SessionID, $RunStatus, $StatusReply)) {
                    if ($RunStatus == \KLF200\RunStatus::EXECUTION_FAILED) {
                        $this->SendDebug('Error', \KLF200\StatusReply::ToString($StatusReply), 0);
                        trigger_error($this->Translate(\KLF200\StatusReply::ToString($StatusReply)), E_USER_NOTICE);
                    }
                }
                return;
            case \KLF200\APICommand::SESSION_FINISHED_NTF:
                $SessionID = unpack('n', substr($APIData->Data, 0, 2))[1];
                $this->SendDebug('SessionID', sprintf('%04X', $SessionID), 0);
                $this->SessionQueueUpdateFinished($SessionID);
                return;
        }
    }

    private function AutoRename(string $Name)
    {
        if ($this->ReadPropertyBoolean(\KLF200\Node\Property::AutoRename)) {
            if ($Name != IPS_GetName($this->InstanceID)) {
                IPS_SetName($this->InstanceID, $Name);
            }
        }
    }

    private function GetSessionId()
    {
        $SessionId = ($this->SessionId + 1) & 0xff;
        $this->SessionId = $SessionId;
        return chr($SessionId);
    }

    /**
     * SendAPIData
     *
     * @param  mixed $APIData
     * @param  int $SessionId
     * @return ?\KLF200\APIData|bool
     */
    private function SendAPIData(\KLF200\APIData $APIData, int $SessionId = -1)
    {
        if ($this->NodeId == chr(-1)) {
            return null;
        }
        $this->SendDebug('ForwardData', $APIData, 1);
        try {
            if (!$this->HasActiveParent()) {
                throw new Exception($this->Translate('Instance has no active parent.'), E_USER_NOTICE);
            }
            if ($this->ReadPropertyBoolean(\KLF200\Node\Property::WaitForFinishSession)) {
                $this->SessionQueueAdd($SessionId);
            }
            /** @var \KLF200\APIData $ResponseAPIData */
            $ret = @$this->SendDataToParent($APIData->ToJSON(\KLF200\GUID::ToGateway));
            $ResponseAPIData = @unserialize($ret);
            $this->SendDebug('Response', $ResponseAPIData, 1);
            if ($ResponseAPIData->isError()) {
                $this->SessionQueueRemove($SessionId);
                trigger_error($this->Translate($ResponseAPIData->ErrorToString()), E_USER_NOTICE);
                return null;
            }
            if ($SessionId == -1) {
                return $ResponseAPIData;
            }
            $ResultStatus = ord($ResponseAPIData->Data[2]); //CommandStatus
            if ($ResultStatus == \KLF200\CommandStatus::COMMAND_REJECTED) {
                $this->SessionQueueRemove($SessionId);
                trigger_error($this->Translate(\KLF200\Status::ToString($ResultStatus)), E_USER_NOTICE);
                return false;
            }
            if (!$this->ReadPropertyBoolean(\KLF200\Node\Property::WaitForFinishSession)) {
                return true;
            }
            $SessionStatus = $this->SessionQueueWaitForFinish($SessionId);
            if ($SessionStatus['RunStatus'] == \KLF200\RunStatus::EXECUTION_FAILED) {
                $this->SendDebug('Error', \KLF200\StatusReply::ToString($SessionStatus['StatusReply']), 0);
                trigger_error($this->Translate(\KLF200\StatusReply::ToString($SessionStatus['StatusReply'])), E_USER_NOTICE);
            }
            return $SessionStatus['RunStatus'] == \KLF200\RunStatus::EXECUTION_COMPLETED;
        } catch (Exception $exc) {
            $this->SessionQueueRemove($SessionId);
            $this->SendDebug('Error', $exc->getMessage(), 0);
            return null;
        }
    }

    //################# SessionQueue
    private function SessionQueueAdd(int $SessionId)
    {
        if ($SessionId == -1) {
            return;
        }
        $this->SendDebug('SessionQueueAdd', sprintf('%04X', $SessionId), 0);
        $this->lock('SessionRunStatus');
        $SessionRunStatus = $this->SessionRunStatus;
        $SessionRunStatus[$SessionId] = [
            'Finished'  => false,
            'RunStatus' => \KLF200\RunStatus::EXECUTION_ACTIVE
        ];
        $this->SessionRunStatus = $SessionRunStatus;
        $this->unlock('SessionRunStatus');
    }

    private function SessionQueueUpdate(int $SessionId, int $RunStatus, int $StatusReply)
    {
        if ($SessionId == -1) {
            return false;
        }
        $this->lock('SessionRunStatus');
        $SessionRunStatus = $this->SessionRunStatus;
        if (!array_key_exists($SessionId, $SessionRunStatus)) {
            $this->unlock('SessionRunStatus');
            return false;
        }
        $SessionRunStatus[$SessionId]['RunStatus'] = $RunStatus;
        $SessionRunStatus[$SessionId]['StatusReply'] = $StatusReply;
        $this->SessionRunStatus = $SessionRunStatus;
        $this->unlock('SessionRunStatus');
        $this->SendDebug('SessionQueueUpdate', sprintf('%04X', $SessionId), 0);
        return true;
    }

    private function SessionQueueUpdateFinished(int $SessionId)
    {
        if ($SessionId == -1) {
            return false;
        }
        $this->lock('SessionRunStatus');
        $SessionRunStatus = $this->SessionRunStatus;
        if (!array_key_exists($SessionId, $SessionRunStatus)) {
            $this->unlock('SessionRunStatus');
            return false;
        }
        $SessionRunStatus[$SessionId]['Finished'] = true;
        $this->SessionRunStatus = $SessionRunStatus;
        $this->unlock('SessionRunStatus');
        $this->SendDebug('SessionQueueUpdateFinished', sprintf('%04X', $SessionId), 0);
        return true;
    }

    private function SessionQueueWaitForFinish(int $SessionId)
    {
        for ($i = 0; $i < 6000; $i++) {
            $this->lock('SessionRunStatus');
            $SessionRunStatus = $this->SessionRunStatus;
            $this->unlock('SessionRunStatus');
            if (!array_key_exists($SessionId, $SessionRunStatus)) {
                return [
                    'RunStatus'  => \KLF200\RunStatus::EXECUTION_FAILED,
                    'StatusReply'=> \KLF200\StatusReply::UNKNOWN_STATUS_REPLY
                ];
            }
            if ($SessionRunStatus[$SessionId]['Finished']) {
                $this->SessionQueueRemove($SessionId);
                $this->SendDebug('SessionQueueWaitForFinish', sprintf('%04X', $SessionId), 0);
                return $SessionRunStatus[$SessionId];
            }
            IPS_Sleep(10);
        }
        $this->SessionQueueRemove($SessionId);
        $this->SendDebug('SessionQueueWaitForFinishTimeout', sprintf('%04X', $SessionId), 0);
        return [
            'RunStatus'  => \KLF200\RunStatus::EXECUTION_FAILED,
            'StatusReply'=> \KLF200\StatusReply::UNKNOWN_STATUS_REPLY
        ];
    }

    private function SessionQueueRemove(int $SessionId)
    {
        if ($SessionId == -1) {
            return;
        }
        $this->lock('SessionRunStatus');
        $SessionRunStatus = $this->SessionRunStatus;
        if (array_key_exists($SessionId, $SessionRunStatus)) {
            $this->SendDebug('SessionQueueRemove', sprintf('%04X', $SessionId), 0);
            unset($SessionRunStatus[$SessionId]);
            $this->SessionRunStatus = $SessionRunStatus;
        }
        $this->unlock('SessionRunStatus');
    }
}
