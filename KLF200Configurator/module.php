<?php

declare(strict_types=1);
require_once __DIR__ . '/../libs/KLF200Class.php';  // diverse Klassen
eval('declare(strict_types=1);namespace KLF200Configurator {?>' . file_get_contents(__DIR__ . '/../libs/helper/BufferHelper.php') . '}');
eval('declare(strict_types=1);namespace KLF200Configurator {?>' . file_get_contents(__DIR__ . '/../libs/helper/SemaphoreHelper.php') . '}');
eval('declare(strict_types=1);namespace KLF200Configurator {?>' . file_get_contents(__DIR__ . '/../libs/helper/DebugHelper.php') . '}');
eval('declare(strict_types=1);namespace KLF200Configurator {?>' . file_get_contents(__DIR__ . '/../libs/helper/ParentIOHelper.php') . '}');

/**
 * KLF200Configurator Klasse
 * Erweitert IPSModule.
 *
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2024 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 *
 * @version       1.00
 *
 * @method void RegisterParent()
 *
 * @property int $ParentID
 * @property array $Nodes
 * @property array $TempNodes
 * @property array $Scenes
 * @property array $TempScenes
 */
class KLF200Configurator extends IPSModule
{
    use \KLF200Configurator\Semaphore,
        \KLF200Configurator\BufferHelper,
        \KLF200Configurator\DebugHelper,
        \KLF200Configurator\InstanceStatus {
            \KLF200Configurator\InstanceStatus::MessageSink as IOMessageSink;
            \KLF200Configurator\InstanceStatus::RequestAction as IORequestAction;
            \KLF200Configurator\DebugHelper::SendDebug as SendDebugTrait;
        }

    /**
     * Interne Funktion des SDK.
     */
    public function Create()
    {
        parent::Create();
        $this->RequireParent(\KLF200\GUID::Gateway);
        //$this->GetNodeInfoIsRunning = false;
        $this->Nodes = [];
        $this->TempNodes = [];
        $this->Scenes = [];
        $this->TempScenes = [];
        $this->ParentID = 0;
    }

    /**
     * Interne Funktion des SDK.
     */
    public function ApplyChanges()
    {
        $this->RegisterMessage(0, IPS_KERNELSTARTED);
        $this->RegisterMessage($this->InstanceID, FM_CONNECT);
        $this->RegisterMessage($this->InstanceID, FM_DISCONNECT);

        parent::ApplyChanges();

        $APICommands = [
            \KLF200\APICommand::GET_ALL_NODES_INFORMATION_NTF,
            \KLF200\APICommand::GET_ALL_NODES_INFORMATION_FINISHED_NTF,
            \KLF200\APICommand::NODE_INFORMATION_CHANGED_NTF,
            \KLF200\APICommand::GET_SCENE_LIST_NTF
        ];

        if (count($APICommands) > 0) {
            foreach ($APICommands as $APICommand) {
                $Lines[] = '.*"Command":' . $APICommand . '.*';
            }
            $Line = implode('|', $Lines);
            $this->SetReceiveDataFilter('(' . $Line . ')');
            $this->SendDebug('FILTER', $Line, 0);
        }
        if (IPS_GetKernelRunlevel() == KR_READY) {
            $this->KernelReady();
        }
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
        }
    }

    public function RequestAction($Ident, $Value)
    {
        if ($this->IORequestAction($Ident, $Value)) {
            return true;
        }
        return false;
    }

    public function DiscoveryNodes()
    {
        $APIData = new \KLF200\APIData(\KLF200\APICommand::CS_DISCOVER_NODES_REQ, "\x00");
        $ResultAPIData = $this->SendAPIData($APIData);
        if ($ResultAPIData->isError()) {
            trigger_error($this->Translate($ResultAPIData->ErrorToString()), E_USER_NOTICE);
            return false;
        }
        $this->UpdateFormField('GatewayCommands', 'visible', false);
        $this->UpdateFormField('Config', 'visible', false);
        $this->UpdateFormField('ProgressLearn', 'visible', true);
        return true;
    }

    public function RebootGateway()
    {
        $APIData = new \KLF200\APIData(\KLF200\APICommand::REBOOT_REQ);
        $ResultAPIData = $this->SendAPIData($APIData);
        if ($ResultAPIData->isError()) {
            trigger_error($this->Translate($ResultAPIData->ErrorToString()), E_USER_NOTICE);
            return false;
        }
        return true;
    }

    public function RemoveNode(int $Node)
    {
        if (($Node < 0) || ($Node > 199)) {
            trigger_error(sprintf($this->Translate('%s out of range.'), 'Node'), E_USER_NOTICE);
            return false;
        }
        $this->UpdateFormField('ProgressRemove', 'visible', true);
        $this->UpdateFormField('RemoveNode', 'visible', false);
        $Data = "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00";
        $Data[intdiv($Node, 8)] = chr(1 << ($Node % 8));
        $APIData = new \KLF200\APIData(\KLF200\APICommand::CS_REMOVE_NODES_REQ, $Data);
        $ResultAPIData = $this->SendAPIData($APIData);
        if ($ResultAPIData->isError()) {
            trigger_error($this->Translate($ResultAPIData->ErrorToString()), E_USER_NOTICE);
            return false;
        }
        return true;
    }

    public function GetConfigurationForm()
    {
        $Form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        $NodeValues = [];
        if (!$this->HasActiveParent()) {
            $Form['actions'][2]['visible'] = true;
            $Form['actions'][2]['popup']['items'][0]['caption'] = 'Instance has no active parent.';
            $Form['actions'][0]['items'][0]['visible'] = false;
        } else {
            $Splitter = IPS_GetInstance($this->InstanceID)['ConnectionID'];
            $IO = IPS_GetInstance($Splitter)['ConnectionID'];
            if ($IO == 0) {
                $Form['actions'][2]['visible'] = true;
                $Form['actions'][2]['popup']['items'][0]['caption'] = 'Splitter has no IO instance.';
            } else {
                $NodeValues = $this->GetNodeConfigFormValues($Splitter);
                $SceneValues = $this->GetSceneConfigFormValues($Splitter);
                $NodeValues = array_merge($NodeValues, $SceneValues);
            }
        }
        $Form['actions'][1]['values'] = $NodeValues;

        $DeleteNodeValues = $this->GetDeleteNodeConfigFormValues();
        $Form['actions'][0]['items'][0]['items'][1]['popup']['items'][1]['values'] = $DeleteNodeValues;
        $Form['actions'][0]['items'][0]['items'][1]['popup']['items'][0]['onClick'] = <<<'EOT'
                if (is_int($RemoveNode['nodeid'])){
                    KLF200_RemoveNode($id,$RemoveNode['nodeid']);
                } else {
                EOT . ' echo "' . $this->Translate('Nothing selected.') . '";}';

        $Form['actions'][0]['items'][0]['items'][2]['onClick'] = <<<'EOT'
                if(KLF200_RebootGateway($id)){
                echo
                EOT . ' "' . $this->Translate('The KLF200 will now reboot.') . '";}';
        $this->SendDebug('FORM', json_encode($Form), 0);
        $this->SendDebug('FORM', json_last_error_msg(), 0);
        return json_encode($Form);
    }

    public function ReceiveData($JSONString)
    {
        $APIData = new \KLF200\APIData($JSONString);
        $this->SendDebug('Event', $APIData, 1);
        $this->ReceiveEvent($APIData);
    }

    /**
     * Wird ausgeführt wenn der Kernel hochgefahren wurde.
     */
    protected function KernelReady()
    {
        $this->RegisterParent();
        if ($this->HasActiveParent()) {
            $this->IOChangeState(IS_ACTIVE);
        }
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
            $this->UpdateFormField('GatewayCommands', 'visible', true);
        } else {
            $this->Nodes = [];
            $this->TempNodes = [];
            $NodeValues = [];
            $Splitter = IPS_GetInstance($this->InstanceID)['ConnectionID'];
            if ($Splitter > 0) {
                $NodeValues = $this->GetNodeConfigFormValues($Splitter);
            }
            $this->UpdateFormField('Config', 'values', json_encode($NodeValues));
            $this->UpdateFormField('RemoveNode', 'values', json_encode([]));
            $this->UpdateFormField('GatewayCommands', 'visible', false);
        }
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
            case \KLF200\APICommand::GET_ALL_NODES_INFORMATION_NTF:
                $NodeID = ord($APIData->Data[0]);
                $Name = trim(substr($APIData->Data, 4, 64));
                $NodeTypeSubType = unpack('n', substr($APIData->Data, 69, 2))[1];
                $this->SendDebug('NodeID (' . $APIData->NodeID . ')', $NodeID, 0);
                $this->SendDebug('Name', $Name, 0);
                $this->SendDebug('NodeTypeSubType', $NodeTypeSubType, 0);
                $this->SendDebug('SerialNumber', substr($APIData->Data, 76, 8), 1);
                $this->SendDebug('BuildNumber', ord($APIData->Data[75]), 0);
                $Nodes = $this->TempNodes;
                $Nodes[$APIData->NodeID] = [
                    'Name'            => $Name,
                    'NodeTypeSubType' => $NodeTypeSubType
                ];
                $this->TempNodes = $Nodes;
                break;
            case \KLF200\APICommand::GET_ALL_NODES_INFORMATION_FINISHED_NTF:
                $this->Nodes = $this->TempNodes;
                $this->TempNodes = [];
                $this->SendDebug('END Nodes', '', 0);
                $Splitter = IPS_GetInstance($this->InstanceID)['ConnectionID'];
                $NodeValues = $this->GetNodeConfigFormValues($Splitter);
                $SceneValues = $this->GetSceneConfigFormValues($Splitter);
                $this->UpdateFormField('Config', 'values', json_encode(array_merge($NodeValues, $SceneValues)));
                $this->UpdateFormField('Config', 'visible', true);
                $this->UpdateFormField('GatewayCommands', 'visible', true);
                $this->UpdateFormField('ProgressLearn', 'visible', false);
                $DeleteNodeValues = $this->GetDeleteNodeConfigFormValues();
                $this->UpdateFormField('RemoveNode', 'values', json_encode($DeleteNodeValues));
                $this->UpdateFormField('RemoveNode', 'visible', true);
                $this->UpdateFormField('ProgressRemove', 'visible', false);
                break;
            case \KLF200\APICommand::NODE_INFORMATION_CHANGED_NTF:
                $Name = trim(substr($APIData->Data, 4, 64));
                $this->SendDebug('Name', $Name, 0);
                $Nodes = $this->Nodes;
                $Nodes[$APIData->NodeID]['Name'] = $Name;
                $this->Nodes = $Nodes;
                break;
            case \KLF200\APICommand::GET_SCENE_LIST_NTF:
                $NumberOfSceneObject = ord($APIData->Data[0]);
                $this->SendDebug('NumberOfObject', $NumberOfSceneObject, 0);
                $ObjectData = substr($APIData->Data, 1);
                for ($index = 0; $index < $NumberOfSceneObject; $index++) {
                    $SceneID = ord($ObjectData[0]);
                    $SceneName = trim(substr($ObjectData, 1, 64));
                    $TempScenes = $this->TempScenes;
                    $TempScenes[$SceneID]['Name'] = $SceneName;
                    $this->TempScenes = $TempScenes;
                    $ObjectData = substr($ObjectData, 65);
                }
                $RemainingNumberOfObject = $ObjectData[0];
                if ($RemainingNumberOfObject == 0) {
                    $this->Scenes = $this->TempScenes;
                    $this->TempScenes = [];
                    $this->SendDebug('END Scenes', '', 0);
                    $Splitter = IPS_GetInstance($this->InstanceID)['ConnectionID'];
                    $NodeValues = $this->GetNodeConfigFormValues($Splitter);
                    $SceneValues = $this->GetSceneConfigFormValues($Splitter);
                    $this->UpdateFormField('Config', 'values', json_encode(array_merge($NodeValues, $SceneValues)));
                }
                break;
        }
    }

    private function GetInstanceList(string $GUID, int $Parent, string $ConfigParam)
    {
        $InstanceIDList = [];
        foreach (IPS_GetInstanceListByModuleID($GUID) as $InstanceID) {
            // Fremde Geräte überspringen
            if (IPS_GetInstance($InstanceID)['ConnectionID'] == $Parent) {
                $InstanceIDList[] = $InstanceID;
            }
        }
        if ($ConfigParam != '') {
            $InstanceIDList = array_flip(array_values($InstanceIDList));
            array_walk($InstanceIDList, [$this, 'GetConfigParam'], $ConfigParam);
        }
        return $InstanceIDList;
    }

    private function GetConfigParam(&$item1, $InstanceID, $ConfigParam)
    {
        $item1 = IPS_GetProperty($InstanceID, $ConfigParam);
    }

    /**
     * Interne Funktion des SDK.
     */
    private function GetNodeConfigFormValues(int $Splitter)
    {
        $FoundNodes = $this->Nodes;
        $this->SendDebug('Found Nodes', $FoundNodes, 0);
        $InstanceIDListNodes = $this->GetInstanceList(\KLF200\GUID::Node, $Splitter, \KLF200\Node\Property::NodeId);
        $this->SendDebug('IPS Nodes', $InstanceIDListNodes, 0);
        $NodeValues = [];
        foreach ($FoundNodes as $NodeID => $Node) {
            $InstanceIDNode = array_search($NodeID, $InstanceIDListNodes);
            if ($InstanceIDNode !== false) {
                $AddValue = [
                    'instanceID' => $InstanceIDNode,
                    'nodeid'     => $NodeID,
                    'name'       => IPS_GetName($InstanceIDNode),
                    'type'       => \KLF200\Node::$SubType[$Node['NodeTypeSubType']],
                    'location'   => stristr(IPS_GetLocation($InstanceIDNode), IPS_GetName($InstanceIDNode), true)
                ];
                unset($InstanceIDListNodes[$InstanceIDNode]);
            } else {
                $AddValue = [
                    'instanceID' => 0,
                    'nodeid'     => $NodeID,
                    'name'       => $Node['Name'],
                    'type'       => \KLF200\Node::$SubType[$Node['NodeTypeSubType']],
                    'location'   => ''
                ];
            }
            $AddValue['create'] = [
                'moduleID'      => \KLF200\GUID::Node,
                'configuration' => [\KLF200\Node\Property::NodeId => $NodeID],
                'location'      => ['Velux KLF200']
            ];

            $NodeValues[] = $AddValue;
        }

        foreach ($InstanceIDListNodes as $InstanceIDNode => $NodeID) {
            $NodeValues[] = [
                'instanceID' => $InstanceIDNode,
                'nodeid'     => $NodeID,
                'name'       => IPS_GetName($InstanceIDNode),
                'type'       => 'unknown',
                'location'   => stristr(IPS_GetLocation($InstanceIDNode), IPS_GetName($InstanceIDNode), true)
            ];
        }
        return $NodeValues;
    }

    /**
     * Interne Funktion des SDK.
     */
    private function GetSceneConfigFormValues(int $Splitter)
    {
        $FoundScenes = $this->Scenes;
        $this->SendDebug('Found Scenes', $FoundScenes, 0);
        $InstanceIDListScenes = $this->GetInstanceList(\KLF200\GUID::Scene, $Splitter, \KLF200\Scene\Property::SceneId);
        $this->SendDebug('IPS Scenes', $InstanceIDListScenes, 0);
        $SceneValues = [];
        foreach ($FoundScenes as $SceneID => $Scene) {
            $InstanceIDScene = array_search($Scene, $InstanceIDListScenes);
            if ($InstanceIDScene !== false) {
                $AddValue = [
                    'instanceID'  => $InstanceIDScene,
                    'sceneid'     => $SceneID,
                    'name'        => IPS_GetName($InstanceIDScene),
                    'type'        => $this->Translate('Scene'),
                    'location'    => stristr(IPS_GetLocation($InstanceIDScene), IPS_GetName($InstanceIDScene), true)
                ];
                unset($InstanceIDListScenes[$InstanceIDScene]);
            } else {
                $AddValue = [
                    'instanceID' => 0,
                    'sceneid'    => $SceneID,
                    'name'       => $Scene['Name'],
                    'type'       => $this->Translate('Scene'),
                    'location'   => ''
                ];
            }
            $AddValue['create'] = [
                'moduleID'      => \KLF200\GUID::Scene,
                'configuration' => [\KLF200\Scene\Property::SceneId => $Scene],
                'location'      => ['Velux KLF200']
            ];

            $SceneValues[] = $AddValue;
        }

        foreach ($InstanceIDListScenes as $InstanceIDScene => $SceneID) {
            $SceneValues[] = [
                'instanceID'  => $InstanceIDScene,
                'sceneid'     => $SceneID,
                'name'        => IPS_GetName($InstanceIDScene),
                'type'        => $this->Translate('Scene'),
                'location'    => stristr(IPS_GetLocation($InstanceIDScene), IPS_GetName($InstanceIDScene), true)
            ];
        }
        return $SceneValues;
    }

    private function GetDeleteNodeConfigFormValues()
    {
        $NodeValues = [];
        foreach ($this->Nodes as $NodeID => $Node) {
            $AddValue = [
                'nodeid' => $NodeID,
                'name'   => $Node['Name'],
                'type'   => \KLF200\Node::$SubType[$Node['NodeTypeSubType']]
            ];
            $NodeValues[] = $AddValue;
        }

        return $NodeValues;
    }

    private function SendAPIData(\KLF200\APIData $APIData)
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
            return $ResponseAPIData;
        } catch (Exception $exc) {
            $this->SendDebug('Error', $exc->getMessage(), 0);
            return new \KLF200\APIData(\KLF200\APICommand::ERROR_NTF, chr(\KLF200\ErrorNTF::TIMEOUT));
        }
    }
}
