<?php

declare(strict_types=1);
require_once __DIR__ . '/../libs/KLF200Class.php';  // diverse Klassen
eval('declare(strict_types=1);namespace KLF200Scene {?>' . file_get_contents(__DIR__ . '/../libs/helper/BufferHelper.php') . '}');
eval('declare(strict_types=1);namespace KLF200Scene {?>' . file_get_contents(__DIR__ . '/../libs/helper/SemaphoreHelper.php') . '}');
eval('declare(strict_types=1);namespace KLF200Scene {?>' . file_get_contents(__DIR__ . '/../libs/helper/DebugHelper.php') . '}');
eval('declare(strict_types=1);namespace KLF200Scene {?>' . file_get_contents(__DIR__ . '/../libs/helper/VariableHelper.php') . '}');
eval('declare(strict_types=1);namespace KLF200Scene {?>' . file_get_contents(__DIR__ . '/../libs/helper/VariableProfileHelper.php') . '}');

/**
 * KLF200Scene Klasse implementiert eine Scene
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
 * @method void UnregisterProfile(string $Name)
 * @property char $SceneId
 * @property int $SessionId
 * @property int[] $SessionRunStatus
 */
class KLF200Scene extends IPSModule
{
    use \KLF200Scene\Semaphore,
        \KLF200Scene\BufferHelper,
        \KLF200Scene\VariableHelper,
        \KLF200Scene\VariableProfileHelper,
        \KLF200Scene\DebugHelper {
            \KLF200Scene\DebugHelper::SendDebug as SendDebugTrait;
        }

    /**
     * Interne Funktion des SDK.
     */
    public function Create()
    {
        parent::Create();
        $this->ConnectParent(\KLF200\GUID::Gateway);
        $this->RegisterPropertyInteger(\KLF200\Scene\Property::SceneId, 0);
        $this->RegisterPropertyBoolean(\KLF200\Scene\Property::AutoRename, false);
        $this->SessionId = 1;
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
        $SceneId = $this->ReadPropertyInteger(\KLF200\Scene\Property::SceneId);
        $this->SceneId = chr($SceneId);
        /*
        foreach (array_keys(\KLF200\APICommand::$EventsToScenes) as $APICommand) {
            $Lines[] = '.*"Command":' . $APICommand . ',"NodeID":-1,.*';
        }
        $Line = implode('|', $Lines);
        $this->SetReceiveDataFilter('(' . $Line . ')');
        $this->SendDebug('FILTER', $Line, 0);
         */

        foreach (array_keys(\KLF200\APICommand::$EventsToScenes) as $APICommand) {
            $Lines[] = '.*"Command":' . $APICommand . '.*';
        }
        $Line = implode('|', $Lines);
        $this->SetReceiveDataFilter('(' . $Line . ')');
        $this->SendDebug('FILTER', $Line, 0);
        $this->RegisterProfileIntegerEx(
            'KLF200.Scene',
            '',
            '',
            '',
            [
                [0, 'Execute', '', -1]
            ]
        );

        $this->RegisterProfileIntegerEx(
            'KLF200.Velocity',
            '',
            '',
            '',
            [
                [0, 'Default', '', -1],
                [1, 'Silent', '', -1],
                [2, 'Fast', '', -1]
            ]
        );
        $this->RegisterVariableInteger(\KLF200\Scene\Variables::Execute, $this->Translate('Scene'), 'KLF200.Scene', 0);
        $this->EnableAction(\KLF200\Scene\Variables::Execute);

        $this->RegisterVariableInteger(\KLF200\Scene\Variables::Velocity, $this->Translate('Velocity'), 'KLF200.Velocity', 0);
        $this->EnableAction(\KLF200\Scene\Variables::Velocity);
    }

    public function RequestAction($Ident, $Value)
    {
        switch ($Ident) {
            case \KLF200\Scene\Variables::Execute:
                $this->StartScene((int) $this->GetValue('Velocity'));
                return;
            case \KLF200\Scene\Variables::Velocity:
                $this->SetValue($Ident, $Value);
                return;
        }
        echo $this->Translate('Invalid Ident');
        return;
    }

    public function StartScene(int $Velocity)
    {
        // Data 1 – 2   Data 3              Data 4          Data 5  Data 6
        // SessionID    CommandOriginator   PriorityLevel   SceneID Velocity
        $Data = $this->SceneId . $this->GetSessionId(); //Data 1-2
        $SessionID = unpack('n', $Data)[1];
        $Data .= chr(1) . chr(3);   // Data 3-4
        $Data .= $this->SceneId;    // Data 5
        $Data .= chr($Velocity);    // Data 6
        $APIData = new \KLF200\APIData(\KLF200\APICommand::ACTIVATE_SCENE_REQ, $Data);
        return $this->SendAPIData($APIData, $SessionID);
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
            if ($Data->isError()) {
                $this->SendDebugTrait('Error', $Data->ErrorToString(), 0);
            } elseif ($Data->Data != '') {
                $this->SendDebugTrait($Message . ':Data', $Data->Data, $Format);
            }
        } else {
            $this->SendDebugTrait($Message, $Data, $Format);
        }
    }

    private function ReceiveEvent(\KLF200\APIData $APIData)
    {
        switch ($APIData->Command) {
            case \KLF200\APICommand::ACTIVATE_SCENE_NTF:
                //todo
                break;
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
        $this->SendDebug('ForwardData', $APIData, 1);
        try {
            if (!$this->HasActiveParent()) {
                throw new Exception($this->Translate('Instance has no active parent.'), E_USER_NOTICE);
            }
            /** @var \KLF200\APIData $ResponseAPIData */
            $ret = @$this->SendDataToParent($APIData->ToJSON(\KLF200\GUID::ToGateway));
            $ResponseAPIData = @unserialize($ret);
            $this->SendDebug('Response', $ResponseAPIData, 1);
            if ($ResponseAPIData->isError()) {
                trigger_error($this->Translate($ResponseAPIData->ErrorToString()), E_USER_NOTICE);
                return null;
            }
            if ($SessionId == -1) {
                return $ResponseAPIData;
            }
            $ResultStatus = ord($ResponseAPIData->Data[0]);
            switch ($ResultStatus) {
                case \KLF200\Status::INVALID_PARAMETERS:
                case \KLF200\Status::REQUEST_REJECTED:
                    trigger_error($this->Translate(\KLF200\Status::ToString($ResultStatus)), E_USER_NOTICE);
                    return false;
                    break;
            }
            return true;
        } catch (Exception $exc) {
            $this->SendDebug('Error', $exc->getMessage(), 0);
            return null;
        }
    }
}
