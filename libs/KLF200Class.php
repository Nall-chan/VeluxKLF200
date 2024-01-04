<?php

declare(strict_types=1);

namespace KLF200{
    class GUID
    {
        // Symcon
        public const DDNS = '{780B2D48-916C-4D59-AD35-5A429B2355A5}';
        public const ClientSocket = '{3CFF0FD9-E306-41DB-9B5A-9D06D38576C3}';

        //KLF200
        public const Gateway = '{725D4DF6-C8FC-463C-823A-D3481A3D7003}';
        public const Node = '{4EBD07B1-2962-4531-AC5F-7944789A9CE5}';
        public const Configurator = '{38724E6E-8202-4D37-9FA7-BDD2EDA79520}';
        //Dataflow
        public const ToGateway = '{7B0F87CC-0408-4283-8E0E-2D48141E42E8}';
        public const ToNodes = '{5242DAEF-EEBD-441F-AB0B-E83C01475B65}';
        public const ToClientSocket = '{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}';
    }

    class APIData
    {
        /**
         * Alle Kommandos als Array.
         *
         * @var int
         */
        public $Command;

        /**
         * Alle Daten des Kommandos.
         *
         * @var string
         */
        public $Data;
        public $LastError = \KLF200\ErrorNTF::NO_ERROR;

        public function __construct($Command, $Data = '')
        {
            if (is_string($Command)) {
                $Data = json_decode($Command, true);
                $this->Command = $Data['Command'];
                $this->Data = utf8_decode($Data['Data']);
                return;
            }
            $this->Command = $Command;
            if ($Command == \KLF200\APICommand::ERROR_NTF) {
                $this->LastError = ord($Data);
                $this->Data = '';
            } else {
                $this->Data = $Data;
            }
        }

        public function ToJSON(string $GUID)
        {
            $Data['DataID'] = $GUID;
            $Data['Command'] = $this->Command;
            $Data['Data'] = utf8_encode($this->Data);
            return json_encode($Data);
        }

        public function GetSLIPData()
        {
            $TransportData = "\x00" . chr(strlen((string) $this->Data) + 3) . pack('n', $this->Command) . (string) $this->Data;

            $cs = 0;
            for ($i = 1; $i < strlen($TransportData); $i++) {
                $cs ^= ord($TransportData[$i]);
            }
            $TransportData .= chr($cs);
            return "\xc0" . str_replace(["\xDB", "\xC0"], ["\xDB\xDD", "\xDB\xDC"], $TransportData) . "\xc0";
        }

        public function isEvent()
        {
            return \KLF200\APICommand::isEvent($this->Command);
        }

        public function isError()
        {
            return $this->LastError != \KLF200\ErrorNTF::NO_ERROR;
        }

        public function ErrorToString()
        {
            return \KLF200\ErrorNTF::ToString($this->LastError);
        }
    }

    class ErrorNTF
    {
        public const NO_ERROR = -1;
        public const NOT_DEFINED = 0; // Not further defined error.
        public const UNKNOWN_COMMAND = 1; // Unknown Command or command is not accepted at this state.
        public const ERROR_ON_FRAME_STRUCTURE = 2; // ERROR on Frame Structure.
        public const BUSY = 7; // Busy. Try again later.
        public const BAD_SYSTEM_TABLE_INDEX = 8; // Bad system table index.
        public const NOT_AUTHENTICATED = 12; // Not authenticated.
        public const TIMEOUT = 99;

        public static function ToString(int $Error)
        {
            switch ($Error) {
                case self::NO_ERROR:
                    return 'No error.';
                case self::TIMEOUT:
                    return 'Timeout.';
                case self::NOT_DEFINED:
                    return 'Not further defined error.';
                case self::UNKNOWN_COMMAND:
                    return 'Unknown command or command is not accepted at this state.';
                case self::ERROR_ON_FRAME_STRUCTURE:
                    return 'Error on Frame Structure.';
                case self::BUSY:
                    return 'Busy. Try again later.';
                case self::BAD_SYSTEM_TABLE_INDEX:
                    return 'Bad system table index.';
                case self::NOT_AUTHENTICATED:
                    return 'Not authenticated.';
            }
        }
    }

    class State
    {
        public const NON_EXECUTING = 0;
        public const ERROR_WHILE_EXECUTION = 1;
        public const NOT_USED = 2;
        public const WAITING_FOR_POWER = 3;
        public const EXECUTING = 4;
        public const DONE = 5;
        public const UNKNOWN = 255;

        public static function ToString(int $State)
        {
            switch ($State) {
                case self::NON_EXECUTING:
                    return 'Parameter is unable to execute.';
                case self::ERROR_WHILE_EXECUTION:
                    return 'Execution has failed.';
                case self::NOT_USED:
                    return 'not used.';
                case self::WAITING_FOR_POWER:
                    return 'waiting for power.';
                case self::EXECUTING:
                    return 'Executing.';
                case self::DONE:
                    return 'done.';
                case self::UNKNOWN:
                    return 'unknown.';
                default:
                    return 'unknown state value: 0x' . sprintf('%02X', $State);
            }
        }
    }

    class RunStatus
    {
        public const EXECUTION_COMPLETED = 0;
        public const EXECUTION_FAILED = 1;
        public const EXECUTION_ACTIVE = 2;

        public static function ToString(int $RunStatus)
        {
            switch ($RunStatus) {
                case self::EXECUTION_COMPLETED:
                    return 'Execution is completed with no errors.';
                case self::EXECUTION_FAILED:
                    return 'Execution has failed.';
                case self::EXECUTION_ACTIVE:
                    return 'Execution is still active.';
            }
        }
    }

    class StatusReply
    {
        public const UNKNOWN_STATUS_REPLY = 0x00;
        public const COMMAND_COMPLETED_OK = 0x01;
        public const NO_CONTACT = 0x02;
        public const MANUALLY_OPERATED = 0x03;
        public const BLOCKED = 0x04;
        public const WRONG_SYSTEMKEY = 0x05;
        public const PRIORITY_LEVEL_LOCKED = 0x06;
        public const REACHED_WRONG_POSITION = 0x07;
        public const ERROR_DURING_EXECUTION = 0x08;
        public const NO_EXECUTION = 0x09;
        public const CALIBRATING = 0x0A;
        public const POWER_CONSUMPTION_TOO_HIGH = 0x0B;
        public const POWER_CONSUMPTION_TOO_LOW = 0x0C;
        public const LOCK_POSITION_OPEN = 0x0D;
        public const MOTION_TIME_TOO_LONG__COMMUNICATION_ENDED = 0x0E;
        public const THERMAL_PROTECTION = 0x0F;
        public const PRODUCT_NOT_OPERATIONAL = 0x10;
        public const FILTER_MAINTENANCE_NEEDED = 0x11;
        public const BATTERY_LEVEL = 0x12;
        public const TARGET_MODIFIED = 0x13;
        public const MODE_NOT_IMPLEMENTED = 0x14;
        public const COMMAND_INCOMPATIBLE_TO_MOVEMENT = 0x15;
        public const USER_ACTION = 0x16;
        public const DEAD_BOLT_ERROR = 0x17;
        public const AUTOMATIC_CYCLE_ENGAGED = 0x18;
        public const WRONG_LOAD_CONNECTED = 0x19;
        public const COLOUR_NOT_REACHABLE = 0x1A;
        public const TARGET_NOT_REACHABLE = 0x1B;
        public const BAD_INDEX_RECEIVED = 0x1C;
        public const COMMAND_OVERRULED = 0x1D;
        public const NODE_WAITING_FOR_POWER = 0x1E;
        public const INFORMATION_CODE = 0xDF;
        public const PARAMETER_LIMITED = 0xE0;
        public const LIMITATION_BY_LOCAL_USER = 0xE1;
        public const LIMITATION_BY_USER = 0xE2;
        public const LIMITATION_BY_RAIN = 0xE3;
        public const LIMITATION_BY_TIMER = 0xE4;
        public const LIMITATION_BY_UPS = 0xE6;
        public const LIMITATION_BY_UNKNOWN_DEVICE = 0xE7;
        public const LIMITATION_BY_SAAC = 0xEA;
        public const LIMITATION_BY_WIND = 0xEB;
        public const LIMITATION_BY_MYSELF = 0xEC;
        public const LIMITATION_BY_AUTOMATIC_CYCLE = 0xED;
        public const LIMITATION_BY_EMERGENCY = 0xEE;

        public static function ToString(int $StatusReply)
        {
            switch ($StatusReply) {
                case self::UNKNOWN_STATUS_REPLY:
                    return 'unknown reply';
                case self::COMMAND_COMPLETED_OK:
                    return 'no errors detected';
                case self::NO_CONTACT:
                    return 'no communication to node';
                case self::MANUALLY_OPERATED:
                    return 'manually operated by a user';
                case self::BLOCKED:
                    return 'node has been blocked by an object';
                case self::WRONG_SYSTEMKEY:
                    return 'the node contains a wrong system key';
                case self::PRIORITY_LEVEL_LOCKED:
                    return 'the node is locked on this priority level';
                case self::REACHED_WRONG_POSITION:
                    return 'node has stopped in another position than expected';
                case self::ERROR_DURING_EXECUTION:
                    return 'an error has occurred during execution of command';
                case self::NO_EXECUTION:
                    return 'no movement of the node parameter';
                case self::CALIBRATING:
                    return 'the node is calibrating the parameters';
                case self::POWER_CONSUMPTION_TOO_HIGH:
                    return 'the node power consumption is too high';
                case self::POWER_CONSUMPTION_TOO_LOW:
                    return 'the node power consumption is too low';
                case self::LOCK_POSITION_OPEN:
                    return 'door lock errors. (Door open during lock command)';
                case self::MOTION_TIME_TOO_LONG__COMMUNICATION_ENDED:
                    return 'the target was not reached in time';
                case self::THERMAL_PROTECTION:
                    return 'the node has gone into thermal protection mode';
                case self::PRODUCT_NOT_OPERATIONAL:
                    return 'the node is not currently operational';
                case self::FILTER_MAINTENANCE_NEEDED:
                    return 'the filter needs maintenance';
                case self::BATTERY_LEVEL:
                    return 'the battery level is low';
                case self::TARGET_MODIFIED:
                    return 'the node has modified the target value of the command';
                case self::MODE_NOT_IMPLEMENTED:
                    return 'this node does not support the mode received';
                case self::COMMAND_INCOMPATIBLE_TO_MOVEMENT:
                    return 'the node is unable to move in the right direction';
                case self::USER_ACTION:
                    return 'dead bolt is manually locked during unlock command';
                case self::DEAD_BOLT_ERROR:
                    return 'dead bolt error';
                case self::AUTOMATIC_CYCLE_ENGAGED:
                    return 'the node has gone into automatic cycle mode';
                case self::WRONG_LOAD_CONNECTED:
                    return 'wrong load on node';
                case self::COLOUR_NOT_REACHABLE:
                    return 'that node is unable to reach received colour code';
                case self::TARGET_NOT_REACHABLE:
                    return 'the node is unable to reach received target position';
                case self::BAD_INDEX_RECEIVED:
                    return 'io-protocol has received an invalid index';
                case self::COMMAND_OVERRULED:
                    return 'that the command was overruled by a new command';
                case self::NODE_WAITING_FOR_POWER:
                    return 'that the node reported waiting for power';
                case self::INFORMATION_CODE:
                    return 'an unknown error code received';
                case self::PARAMETER_LIMITED:
                    return 'the parameter was limited by an unknown device';
                case self::LIMITATION_BY_LOCAL_USER:
                    return 'the parameter was limited by local button';
                case self::LIMITATION_BY_USER:
                    return 'the parameter was limited by a remote control';
                case self::LIMITATION_BY_RAIN:
                    return 'the parameter was limited by a rain sensor';
                case self::LIMITATION_BY_TIMER:
                    return 'the parameter was limited by a timer';
                case self::LIMITATION_BY_UPS:
                    return 'the parameter was limited by a power supply';
                case self::LIMITATION_BY_UNKNOWN_DEVICE:
                    return 'the parameter was limited by an unknown device';
                case self::LIMITATION_BY_SAAC:
                    return 'the parameter was limited by a standalone automatic controller';
                case self::LIMITATION_BY_WIND:
                    return 'the parameter was limited by a wind sensor';
                case self::LIMITATION_BY_MYSELF:
                    return 'the parameter was limited by the node itself';
                case self::LIMITATION_BY_AUTOMATIC_CYCLE:
                    return 'the parameter was limited by an automatic cycle';
                case self::LIMITATION_BY_EMERGENCY:
                    return 'the parameter was limited by an emergency';
            }
        }
    }

    class APICommand
    {
        public const ERROR_NTF = 0x0000; //Provides information on what triggered the error.
        public const REBOOT_REQ = 0x0001; //Request gateway to reboot.
        public const REBOOT_CFM = 0x0002; //Acknowledge to REBOOT_REQ command.
        public const SET_FACTORY_DEFAULT_REQ = 0x0003; //Request gateway to clear system table, scene table and set Ethernet settings to factory default. Gateway will reboot.
        public const SET_FACTORY_DEFAULT_CFM = 0x0004; //Acknowledge to SET_FACTORY_DEFAULT_REQ command.
        public const GET_VERSION_REQ = 0x0008; //Request version information.
        public const GET_VERSION_CFM = 0x0009; //Acknowledge to GET_VERSION_REQ command.
        public const GET_PROTOCOL_VERSION_REQ = 0x000A; //Request KLF 200 API protocol version.
        public const GET_PROTOCOL_VERSION_CFM = 0x000B; //Acknowledge to GET_PROTOCOL_VERSION_REQ command.
        public const GET_STATE_REQ = 0x000C; //Request the state of the gateway
        public const GET_STATE_CFM = 0x000D; //Acknowledge to GET_STATE_REQ command.
        public const LEAVE_LEARN_STATE_REQ = 0x000E; //Request gateway to leave learn state.
        public const LEAVE_LEARN_STATE_CFM = 0x000F; //Acknowledge to LEAVE_LEARN_STATE_REQ command.
        public const GET_NETWORK_SETUP_REQ = 0x00E0; //Request network parameters.
        public const GET_NETWORK_SETUP_CFM = 0x00E1; //Acknowledge to GET_NETWORK_SETUP_REQ.
        public const SET_NETWORK_SETUP_REQ = 0x00E2; //Set network parameters.
        public const SET_NETWORK_SETUP_CFM = 0x00E3; //Acknowledge to SET_NETWORK_SETUP_REQ.
        public const CS_GET_SYSTEMTABLE_DATA_REQ = 0x0100; //Request a list of nodes in the gateways system table.
        public const CS_GET_SYSTEMTABLE_DATA_CFM = 0x0101; //Acknowledge to CS_GET_SYSTEMTABLE_DATA_REQ
        public const CS_GET_SYSTEMTABLE_DATA_NTF = 0x0102; //Acknowledge to CS_GET_SYSTEM_TABLE_DATA_REQList of nodes in the gateways systemtable.
        public const CS_DISCOVER_NODES_REQ = 0x0103; //Start CS DiscoverNodes macro in KLF200.
        public const CS_DISCOVER_NODES_CFM = 0x0104; //Acknowledge to CS_DISCOVER_NODES_REQ command.
        public const CS_DISCOVER_NODES_NTF = 0x0105; //Acknowledge to CS_DISCOVER_NODES_REQ command.
        public const CS_REMOVE_NODES_REQ = 0x0106; //Remove one or more nodes in the systemtable.
        public const CS_REMOVE_NODES_CFM = 0x0107; //Acknowledge to CS_REMOVE_NODES_REQ.
        public const CS_VIRGIN_STATE_REQ = 0x0108; //Clear systemtable and delete system key.
        public const CS_VIRGIN_STATE_CFM = 0x0109; //Acknowledge to CS_VIRGIN_STATE_REQ.
        public const CS_CONTROLLER_COPY_REQ = 0x010A; //Setup KLF200 to get or give a system to or from another io-homecontrol® remote control. By a system means all nodes in the systemtable and the system key.
        public const CS_CONTROLLER_COPY_CFM = 0x010B; //Acknowledge to CS_CONTROLLER_COPY_REQ.
        public const CS_CONTROLLER_COPY_NTF = 0x010C; //Acknowledge to CS_CONTROLLER_COPY_REQ.
        public const CS_CONTROLLER_COPY_CANCEL_NTF = 0x010D; //Cancellation of system copy to other controllers.
        public const CS_RECEIVE_KEY_REQ = 0x010E; //Receive system key from another controller.
        public const CS_RECEIVE_KEY_CFM = 0x010F; //Acknowledge to CS_RECEIVE_KEY_REQ.
        public const CS_RECEIVE_KEY_NTF = 0x0110; //Acknowledge to CS_RECEIVE_KEY_REQ with status.
        public const CS_PGC_JOB_NTF = 0x0111; //Information on Product Generic Configuration job initiated by press on PGC button.
        public const CS_SYSTEM_TABLE_UPDATE_NTF = 0x0112; //Broadcasted to all clients and gives information about added and removed actuator nodes in system table.
        public const CS_GENERATE_NEW_KEY_REQ = 0x0113; //Generate new system key and update actuators in systemtable.
        public const CS_GENERATE_NEW_KEY_CFM = 0x0114; //Acknowledge to CS_GENERATE_NEW_KEY_REQ.
        public const CS_GENERATE_NEW_KEY_NTF = 0x0115; //Acknowledge to CS_GENERATE_NEW_KEY_REQ with status.
        public const CS_REPAIR_KEY_REQ = 0x0116; //Update key in actuators holding an old key.
        public const CS_REPAIR_KEY_CFM = 0x0117; //Acknowledge to CS_REPAIR_KEY_REQ.
        public const CS_REPAIR_KEY_NTF = 0x0118; //Acknowledge to CS_REPAIR_KEY_REQ with status.
        public const CS_ACTIVATE_CONFIGURATION_MODE_REQ = 0x0119; //Request one or more actuator to open for configuration.
        public const CS_ACTIVATE_CONFIGURATION_MODE_CFM = 0x011A; //Acknowledge to CS_ACTIVATE_CONFIGURATION_MODE_REQ.
        public const GET_NODE_INFORMATION_REQ = 0x0200; //Request extended information of one specific actuator node.
        public const GET_NODE_INFORMATION_CFM = 0x0201; //Acknowledge to GET_NODE_INFORMATION_REQ.
        public const GET_NODE_INFORMATION_NTF = 0x0210; //Acknowledge to GET_NODE_INFORMATION_REQ.
        public const GET_ALL_NODES_INFORMATION_REQ = 0x0202; //Request extended information of all nodes.
        public const GET_ALL_NODES_INFORMATION_CFM = 0x0203; //Acknowledge to GET_ALL_NODES_INFORMATION_REQ
        public const GET_ALL_NODES_INFORMATION_NTF = 0x0204; //Acknowledge to GET_ALL_NODES_INFORMATION_REQ. Holds node information
        public const GET_ALL_NODES_INFORMATION_FINISHED_NTF = 0x0205; //Acknowledge to GET_ALL_NODES_INFORMATION_REQ. No more nodes.
        public const SET_NODE_VARIATION_REQ = 0x0206; //Set node variation.
        public const SET_NODE_VARIATION_CFM = 0x0207; //Acknowledge to SET_NODE_VARIATION_REQ.
        public const SET_NODE_NAME_REQ = 0x0208; //Set node name.
        public const SET_NODE_NAME_CFM = 0x0209; //Acknowledge to SET_NODE_NAME_REQ.
        public const SET_NODE_VELOCITY_REQ = 0x020A; //Set node velocity.
        public const SET_NODE_VELOCITY_CFM = 0x020B; //Acknowledge to SET_NODE_VELOCITY_REQ.
        public const NODE_INFORMATION_CHANGED_NTF = 0x020C; //Information has been updated.
        public const NODE_STATE_POSITION_CHANGED_NTF = 0x0211; //Information has been updated.
        public const SET_NODE_ORDER_AND_PLACEMENT_REQ = 0x020D; //Set search order and room placement.
        public const SET_NODE_ORDER_AND_PLACEMENT_CFM = 0x020E; //Acknowledge to SET_NODE_ORDER_AND_PLACEMENT_REQ.
        public const GET_GROUP_INFORMATION_REQ = 0x0220; //Request information about all defined groups.
        public const GET_GROUP_INFORMATION_CFM = 0x0221; //Acknowledge to GET_GROUP_INFORMATION_REQ.
        public const GET_GROUP_INFORMATION_NTF = 0x0230; //Acknowledge to GET_NODE_INFORMATION_REQ.
        public const SET_GROUP_INFORMATION_REQ = 0x0222; //Change an existing group.
        public const SET_GROUP_INFORMATION_CFM = 0x0223; //Acknowledge to SET_GROUP_INFORMATION_REQ.
        public const GROUP_INFORMATION_CHANGED_NTF = 0x0224; //Broadcast to all, about group information of a group has been changed.
        public const DELETE_GROUP_REQ = 0x0225; //Delete a group.
        public const DELETE_GROUP_CFM = 0x0226; //Acknowledge to DELETE_GROUP_INFORMATION_REQ.
        public const NEW_GROUP_REQ = 0x0227; //Request new group to be created.
        public const NEW_GROUP_CFM = 0x0228;
        public const GET_ALL_GROUPS_INFORMATION_REQ = 0x0229; //Request information about all defined groups.
        public const GET_ALL_GROUPS_INFORMATION_CFM = 0x022A; //Acknowledge to GET_ALL_GROUPS_INFORMATION_REQ.
        public const GET_ALL_GROUPS_INFORMATION_NTF = 0x022B; //Acknowledge to GET_ALL_GROUPS_INFORMATION_REQ.
        public const GET_ALL_GROUPS_INFORMATION_FINISHED_NTF = 0x022C; //Acknowledge to GET_ALL_GROUPS_INFORMATION_REQ.
        public const GROUP_DELETED_NTF = 0x022D; //GROUP_DELETED_NTF is broadcasted to all, when a group has been removed.
        public const HOUSE_STATUS_MONITOR_ENABLE_REQ = 0x0240; //Enable house status monitor.
        public const HOUSE_STATUS_MONITOR_ENABLE_CFM = 0x0241; //Acknowledge to HOUSE_STATUS_MONITOR_ENABLE_REQ.
        public const HOUSE_STATUS_MONITOR_DISABLE_REQ = 0x0242; //Disable house status monitor.
        public const HOUSE_STATUS_MONITOR_DISABLE_CFM = 0x0243; //Acknowledge to HOUSE_STATUS_MONITOR_DISABLE_REQ.
        public const COMMAND_SEND_REQ = 0x0300; //Send activating command direct to one or more io-homecontrol® nodes.
        public const COMMAND_SEND_CFM = 0x0301; //Acknowledge to COMMAND_SEND_REQ.
        public const COMMAND_RUN_STATUS_NTF = 0x0302; //Gives run status for io-homecontrol® node.
        public const COMMAND_REMAINING_TIME_NTF = 0x0303; //Gives remaining time before io-homecontrol® node enter target position.
        public const SESSION_FINISHED_NTF = 0x0304; //Command send, Status request, Wink, Mode or Stop session is finished.
        public const STATUS_REQUEST_REQ = 0x0305; //Get status request from one or more io-homecontrol® nodes.
        public const STATUS_REQUEST_CFM = 0x0306; //Acknowledge to STATUS_REQUEST_REQ.
        public const STATUS_REQUEST_NTF = 0x0307; //Acknowledge to STATUS_REQUEST_REQ. Status request from one or more io-homecontrol® nodes.
        public const WINK_SEND_REQ = 0x0308; //Request from one or more io-homecontrol® nodes to Wink.
        public const WINK_SEND_CFM = 0x0309; //Acknowledge to WINK_SEND_REQ
        public const WINK_SEND_NTF = 0x030A; //Status info for performed wink request.
        public const SET_LIMITATION_REQ = 0x0310; //Set a parameter limitation in an actuator.
        public const SET_LIMITATION_CFM = 0x0311; //Acknowledge to SET_LIMITATION_REQ.
        public const GET_LIMITATION_STATUS_REQ = 0x0312; //Get parameter limitation in an actuator.
        public const GET_LIMITATION_STATUS_CFM = 0x0313; //Acknowledge to GET_LIMITATION_STATUS_REQ.
        public const LIMITATION_STATUS_NTF = 0x0314; //Hold information about limitation.
        public const MODE_SEND_REQ = 0x0320; //Send Activate Mode to one or more io-homecontrol® nodes.
        public const MODE_SEND_CFM = 0x0321; //Acknowledge to MODE_SEND_REQ
        public const MODE_SEND_NTF = 0x0322; //Notify with Mode activation info.
        public const INITIALIZE_SCENE_REQ = 0x0400; //Prepare gateway to record a scene.
        public const INITIALIZE_SCENE_CFM = 0x0401; //Acknowledge to INITIALIZE_SCENE_REQ.
        public const INITIALIZE_SCENE_NTF = 0x0402; //Acknowledge to INITIALIZE_SCENE_REQ.
        public const INITIALIZE_SCENE_CANCEL_REQ = 0x0403; //Cancel record scene process.
        public const INITIALIZE_SCENE_CANCEL_CFM = 0x0404; //Acknowledge to INITIALIZE_SCENE_CANCEL_REQ command.
        public const RECORD_SCENE_REQ = 0x0405; //Store actuator positions changes since INITIALIZE_SCENE, as a scene.
        public const RECORD_SCENE_CFM = 0x0406; //Acknowledge to RECORD_SCENE_REQ.
        public const RECORD_SCENE_NTF = 0x0407; //Acknowledge to RECORD_SCENE_REQ.
        public const DELETE_SCENE_REQ = 0x0408; //Delete a recorded scene.
        public const DELETE_SCENE_CFM = 0x0409; //Acknowledge to DELETE_SCENE_REQ.
        public const RENAME_SCENE_REQ = 0x040A; //Request a scene to be renamed.
        public const RENAME_SCENE_CFM = 0x040B; //Acknowledge to RENAME_SCENE_REQ.
        public const GET_SCENE_LIST_REQ = 0x040C; //Request a list of scenes.
        public const GET_SCENE_LIST_CFM = 0x040D; //Acknowledge to GET_SCENE_LIST.
        public const GET_SCENE_LIST_NTF = 0x040E; //Acknowledge to GET_SCENE_LIST.
        public const GET_SCENE_INFORMATION_REQ = 0x040F; //Request extended information for one given scene.
        public const GET_SCENE_INFORMATION_CFM = 0x0410; //Acknowledge to GET_SCENE_INFORMATION_REQ.
        public const GET_SCENE_INFORMATION_NTF = 0x0411; //Acknowledge to GET_SCENE_INFORMATION_REQ.
        public const ACTIVATE_SCENE_REQ = 0x0412; //Request gateway to enter a scene.
        public const ACTIVATE_SCENE_CFM = 0x0413; //Acknowledge to ACTIVATE_SCENE_REQ.
        public const STOP_SCENE_REQ = 0x0415; //Request all nodes in a given scene to stop at their current position.
        public const STOP_SCENE_CFM = 0x0416; //Acknowledge to STOP_SCENE_REQ.
        public const SCENE_INFORMATION_CHANGED_NTF = 0x0419; //A scene has either been changed or removed.
        public const ACTIVATE_PRODUCTGROUP_REQ = 0x0447; //Activate a product group in a given direction.
        public const ACTIVATE_PRODUCTGROUP_CFM = 0x0448; //Acknowledge to ACTIVATE_PRODUCTGROUP_REQ.
        public const ACTIVATE_PRODUCTGROUP_NTF = 0x0449; //Acknowledge to ACTIVATE_PRODUCTGROUP_REQ.
        public const GET_CONTACT_INPUT_LINK_LIST_REQ = 0x0460; //Get list of assignments to all Contact Input to scene or product group.
        public const GET_CONTACT_INPUT_LINK_LIST_CFM = 0x0461; //Acknowledge to GET_CONTACT_INPUT_LINK_LIST_REQ.
        public const SET_CONTACT_INPUT_LINK_REQ = 0x0462; //Set a link from a Contact Input to a scene or product group.
        public const SET_CONTACT_INPUT_LINK_CFM = 0x0463; //Acknowledge to SET_CONTACT_INPUT_LINK_REQ.
        public const REMOVE_CONTACT_INPUT_LINK_REQ = 0x0464; //Remove a link from a Contact Input to a scene.
        public const REMOVE_CONTACT_INPUT_LINK_CFM = 0x0465; //Acknowledge to REMOVE_CONTACT_INPUT_LINK_REQ.
        public const GET_ACTIVATION_LOG_HEADER_REQ = 0x0500; //Request header from activation log.
        public const GET_ACTIVATION_LOG_HEADER_CFM = 0x0501; //Confirm header from activation log.
        public const CLEAR_ACTIVATION_LOG_REQ = 0x0502; //Request clear all data in activation log.
        public const CLEAR_ACTIVATION_LOG_CFM = 0x0503; //Confirm clear all data in activation log.
        public const GET_ACTIVATION_LOG_LINE_REQ = 0x0504; //Request line from activation log.
        public const GET_ACTIVATION_LOG_LINE_CFM = 0x0505; //Confirm line from activation log.
        public const ACTIVATION_LOG_UPDATED_NTF = 0x0506; //Confirm line from activation log.
        public const GET_MULTIPLE_ACTIVATION_LOG_LINES_REQ = 0x0507; //Request lines from activation log.
        public const GET_MULTIPLE_ACTIVATION_LOG_LINES_NTF = 0x0508; //Error log data from activation log.
        public const GET_MULTIPLE_ACTIVATION_LOG_LINES_CFM = 0x0509; //Confirm lines from activation log.
        public const SET_UTC_REQ = 0x2000; //Request to set UTC time.
        public const SET_UTC_CFM = 0x2001; //Acknowledge to SET_UTC_REQ.
        public const RTC_SET_TIME_ZONE_REQ = 0x2002; //Set time zone and daylight savings rules.
        public const RTC_SET_TIME_ZONE_CFM = 0x2003; //Acknowledge to RTC_SET_TIME_ZONE_REQ.
        public const GET_LOCAL_TIME_REQ = 0x2004; //Request the local time based on current time zone and daylight savings rules.
        public const GET_LOCAL_TIME_CFM = 0x2005; //Acknowledge to RTC_SET_TIME_ZONE_REQ.
        public const IGNORE_2120 = 0x2120;
        public const IGNORE_2121 = 0x2121;
        public const IGNORE_2122 = 0x2122;
        public const IGNORE_2123 = 0x2123;
        public const IGNORE_2124 = 0x2124;
        public const PASSWORD_ENTER_REQ = 0x3000; //Enter password to authenticate request
        public const PASSWORD_ENTER_CFM = 0x3001; //Acknowledge to PASSWORD_ENTER_REQ
        public const PASSWORD_CHANGE_REQ = 0x3002; //Request password change.
        public const PASSWORD_CHANGE_CFM = 0x3003; //Acknowledge to PASSWORD_CHANGE_REQ.
        public const PASSWORD_CHANGE_NTF = 0x3004; //Acknowledge to PASSWORD_CHANGE_REQ. Broadcasted to all connected clients.

        public static $NotifyCommand = [
            self::CS_GET_SYSTEMTABLE_DATA_NTF,
            self::CS_DISCOVER_NODES_NTF,
            self::CS_CONTROLLER_COPY_NTF,
            self::CS_CONTROLLER_COPY_CANCEL_NTF,
            self::CS_RECEIVE_KEY_NTF,
            self::CS_PGC_JOB_NTF,
            self::CS_SYSTEM_TABLE_UPDATE_NTF,
            self::CS_GENERATE_NEW_KEY_NTF,
            self::CS_REPAIR_KEY_NTF,
            self::GET_NODE_INFORMATION_NTF,
            self::GET_ALL_NODES_INFORMATION_NTF,
            self::GET_ALL_NODES_INFORMATION_FINISHED_NTF,
            self::NODE_INFORMATION_CHANGED_NTF,
            self::NODE_STATE_POSITION_CHANGED_NTF,
            self::GET_GROUP_INFORMATION_NTF,
            self::GROUP_INFORMATION_CHANGED_NTF,
            self::GET_ALL_GROUPS_INFORMATION_NTF,
            self::GET_ALL_GROUPS_INFORMATION_FINISHED_NTF,
            self::GROUP_DELETED_NTF,
            self::COMMAND_RUN_STATUS_NTF,
            self::COMMAND_REMAINING_TIME_NTF,
            self::SESSION_FINISHED_NTF,
            self::STATUS_REQUEST_NTF,
            self::WINK_SEND_NTF,
            self::LIMITATION_STATUS_NTF,
            self::MODE_SEND_NTF,
            self::INITIALIZE_SCENE_NTF,
            self::RECORD_SCENE_NTF,
            self::GET_SCENE_LIST_NTF,
            self::GET_SCENE_INFORMATION_NTF,
            self::SCENE_INFORMATION_CHANGED_NTF,
            self::ACTIVATE_PRODUCTGROUP_NTF,
            self::ACTIVATION_LOG_UPDATED_NTF,
            self::GET_MULTIPLE_ACTIVATION_LOG_LINES_NTF,
            self::PASSWORD_CHANGE_NTF,
            self::IGNORE_2120,
            self::IGNORE_2121,
            self::IGNORE_2122,
            self::IGNORE_2123,
            self::IGNORE_2124
        ];

        public static function isEvent(int $APICommand)
        {
            return in_array($APICommand, self::$NotifyCommand);
        }

        public static function ToString($APICommand)
        {
            switch ($APICommand) {
                case self::ERROR_NTF:
                    return 'ERROR_NTF';
                case self::REBOOT_REQ:
                    return 'REBOOT_REQ';
                case self::REBOOT_CFM:
                    return 'REBOOT_CFM';
                case self::SET_FACTORY_DEFAULT_REQ:
                    return 'SET_FACTORY_DEFAULT_REQ';
                case self::SET_FACTORY_DEFAULT_CFM:
                    return 'SET_FACTORY_DEFAULT_CFM';
                case self::GET_VERSION_REQ:
                    return 'GET_VERSION_REQ';
                case self::GET_VERSION_CFM:
                    return 'GET_VERSION_CFM';
                case self::GET_PROTOCOL_VERSION_REQ:
                    return 'GET_PROTOCOL_VERSION_REQ';
                case self::GET_PROTOCOL_VERSION_CFM:
                    return 'GET_PROTOCOL_VERSION_CFM';
                case self::GET_STATE_REQ:
                    return 'GET_STATE_REQ';
                case self::GET_STATE_CFM:
                    return 'GET_STATE_CFM';
                case self::LEAVE_LEARN_STATE_REQ:
                    return 'LEAVE_LEARN_STATE_REQ';
                case self::LEAVE_LEARN_STATE_CFM:
                    return 'LEAVE_LEARN_STATE_CFM';
                case self::GET_NETWORK_SETUP_REQ:
                    return 'GET_NETWORK_SETUP_REQ';
                case self::GET_NETWORK_SETUP_CFM:
                    return 'GET_NETWORK_SETUP_CFM';
                case self::SET_NETWORK_SETUP_REQ:
                    return 'SET_NETWORK_SETUP_REQ';
                case self::SET_NETWORK_SETUP_CFM:
                    return 'SET_NETWORK_SETUP_CFM';
                case self::CS_GET_SYSTEMTABLE_DATA_REQ:
                    return 'CS_GET_SYSTEMTABLE_DATA_REQ';
                case self::CS_GET_SYSTEMTABLE_DATA_CFM:
                    return 'CS_GET_SYSTEMTABLE_DATA_CFM';
                case self::CS_GET_SYSTEMTABLE_DATA_NTF:
                    return 'CS_GET_SYSTEMTABLE_DATA_NTF';
                case self::CS_DISCOVER_NODES_REQ:
                    return 'CS_DISCOVER_NODES_REQ';
                case self::CS_DISCOVER_NODES_CFM:
                    return 'CS_DISCOVER_NODES_CFM';
                case self::CS_DISCOVER_NODES_NTF:
                    return 'CS_DISCOVER_NODES_NTF';
                case self::CS_REMOVE_NODES_REQ:
                    return 'CS_REMOVE_NODES_REQ';
                case self::CS_REMOVE_NODES_CFM:
                    return 'CS_REMOVE_NODES_CFM';
                case self::CS_VIRGIN_STATE_REQ:
                    return 'CS_VIRGIN_STATE_REQ';
                case self::CS_VIRGIN_STATE_CFM:
                    return 'CS_VIRGIN_STATE_CFM';
                case self::CS_CONTROLLER_COPY_REQ:
                    return 'CS_CONTROLLER_COPY_REQ';
                case self::CS_CONTROLLER_COPY_CFM:
                    return 'CS_CONTROLLER_COPY_CFM';
                case self::CS_CONTROLLER_COPY_NTF:
                    return 'CS_CONTROLLER_COPY_NTF';
                case self::CS_CONTROLLER_COPY_CANCEL_NTF:
                    return 'CS_CONTROLLER_COPY_CANCEL_NTF';
                case self::CS_RECEIVE_KEY_REQ:
                    return 'CS_RECEIVE_KEY_REQ';
                case self::CS_RECEIVE_KEY_CFM:
                    return 'CS_RECEIVE_KEY_CFM';
                case self::CS_RECEIVE_KEY_NTF:
                    return 'CS_RECEIVE_KEY_NTF';
                case self::CS_PGC_JOB_NTF:
                    return 'CS_PGC_JOB_NTF';
                case self::CS_SYSTEM_TABLE_UPDATE_NTF:
                    return 'CS_SYSTEM_TABLE_UPDATE_NTF';
                case self::CS_GENERATE_NEW_KEY_REQ:
                    return 'CS_GENERATE_NEW_KEY_REQ';
                case self::CS_GENERATE_NEW_KEY_CFM:
                    return 'CS_GENERATE_NEW_KEY_CFM';
                case self::CS_GENERATE_NEW_KEY_NTF:
                    return 'CS_GENERATE_NEW_KEY_NTF';
                case self::CS_REPAIR_KEY_REQ:
                    return 'CS_REPAIR_KEY_REQ';
                case self::CS_REPAIR_KEY_CFM:
                    return 'CS_REPAIR_KEY_CFM';
                case self::CS_REPAIR_KEY_NTF:
                    return 'CS_REPAIR_KEY_NTF';
                case self::CS_ACTIVATE_CONFIGURATION_MODE_REQ:
                    return 'CS_ACTIVATE_CONFIGURATION_MODE_REQ';
                case self::CS_ACTIVATE_CONFIGURATION_MODE_CFM:
                    return 'CS_ACTIVATE_CONFIGURATION_MODE_CFM';
                case self::GET_NODE_INFORMATION_REQ:
                    return 'GET_NODE_INFORMATION_REQ';
                case self::GET_NODE_INFORMATION_CFM:
                    return 'GET_NODE_INFORMATION_CFM';
                case self::GET_NODE_INFORMATION_NTF:
                    return 'GET_NODE_INFORMATION_NTF';
                case self::GET_ALL_NODES_INFORMATION_REQ:
                    return 'GET_ALL_NODES_INFORMATION_REQ';
                case self::GET_ALL_NODES_INFORMATION_CFM:
                    return 'GET_ALL_NODES_INFORMATION_CFM';
                case self::GET_ALL_NODES_INFORMATION_NTF:
                    return 'GET_ALL_NODES_INFORMATION_NTF';
                case self::GET_ALL_NODES_INFORMATION_FINISHED_NTF:
                    return 'GET_ALL_NODES_INFORMATION_FINISHED_NTF';
                case self::SET_NODE_VARIATION_REQ:
                    return 'SET_NODE_VARIATION_REQ';
                case self::SET_NODE_VARIATION_CFM:
                    return 'SET_NODE_VARIATION_CFM';
                case self::SET_NODE_NAME_REQ:
                    return 'SET_NODE_NAME_REQ';
                case self::SET_NODE_NAME_CFM:
                    return 'SET_NODE_NAME_CFM';
                case self::SET_NODE_VELOCITY_REQ:
                    return 'SET_NODE_VELOCITY_REQ';
                case self::SET_NODE_VELOCITY_CFM:
                    return 'SET_NODE_VELOCITY_CFM';
                case self::NODE_INFORMATION_CHANGED_NTF:
                    return 'NODE_INFORMATION_CHANGED_NTF';
                case self::NODE_STATE_POSITION_CHANGED_NTF:
                    return 'NODE_STATE_POSITION_CHANGED_NTF';
                case self::SET_NODE_ORDER_AND_PLACEMENT_REQ:
                    return 'SET_NODE_ORDER_AND_PLACEMENT_REQ';
                case self::SET_NODE_ORDER_AND_PLACEMENT_CFM:
                    return 'SET_NODE_ORDER_AND_PLACEMENT_CFM';
                case self::GET_GROUP_INFORMATION_REQ:
                    return 'GET_GROUP_INFORMATION_REQ';
                case self::GET_GROUP_INFORMATION_CFM:
                    return 'GET_GROUP_INFORMATION_CFM';
                case self::GET_GROUP_INFORMATION_NTF:
                    return 'GET_GROUP_INFORMATION_NTF';
                case self::SET_GROUP_INFORMATION_REQ:
                    return 'SET_GROUP_INFORMATION_REQ';
                case self::SET_GROUP_INFORMATION_CFM:
                    return 'SET_GROUP_INFORMATION_CFM';
                case self::GROUP_INFORMATION_CHANGED_NTF:
                    return 'GROUP_INFORMATION_CHANGED_NTF';
                case self::DELETE_GROUP_REQ:
                    return 'DELETE_GROUP_REQ';
                case self::DELETE_GROUP_CFM:
                    return 'DELETE_GROUP_CFM';
                case self::NEW_GROUP_REQ:
                    return 'NEW_GROUP_REQ';
                case self::NEW_GROUP_CFM:
                    return 'NEW_GROUP_CFM';
                case self::GET_ALL_GROUPS_INFORMATION_REQ:
                    return 'GET_ALL_GROUPS_INFORMATION_REQ';
                case self::GET_ALL_GROUPS_INFORMATION_CFM:
                    return 'GET_ALL_GROUPS_INFORMATION_CFM';
                case self::GET_ALL_GROUPS_INFORMATION_NTF:
                    return 'GET_ALL_GROUPS_INFORMATION_NTF';
                case self::GET_ALL_GROUPS_INFORMATION_FINISHED_NTF:
                    return 'GET_ALL_GROUPS_INFORMATION_FINISHED_NTF';
                case self::GROUP_DELETED_NTF:
                    return 'GROUP_DELETED_NTF';
                case self::HOUSE_STATUS_MONITOR_ENABLE_REQ:
                    return 'HOUSE_STATUS_MONITOR_ENABLE_REQ';
                case self::HOUSE_STATUS_MONITOR_ENABLE_CFM:
                    return 'HOUSE_STATUS_MONITOR_ENABLE_CFM';
                case self::HOUSE_STATUS_MONITOR_DISABLE_REQ:
                    return 'HOUSE_STATUS_MONITOR_DISABLE_REQ';
                case self::HOUSE_STATUS_MONITOR_DISABLE_CFM:
                    return 'HOUSE_STATUS_MONITOR_DISABLE_CFM';
                case self::COMMAND_SEND_REQ:
                    return 'COMMAND_SEND_REQ';
                case self::COMMAND_SEND_CFM:
                    return 'COMMAND_SEND_CFM';
                case self::COMMAND_RUN_STATUS_NTF:
                    return 'COMMAND_RUN_STATUS_NTF';
                case self::COMMAND_REMAINING_TIME_NTF:
                    return 'COMMAND_REMAINING_TIME_NTF';
                case self::SESSION_FINISHED_NTF:
                    return 'SESSION_FINISHED_NTF';
                case self::STATUS_REQUEST_REQ:
                    return 'STATUS_REQUEST_REQ';
                case self::STATUS_REQUEST_CFM:
                    return 'STATUS_REQUEST_CFM';
                case self::STATUS_REQUEST_NTF:
                    return 'STATUS_REQUEST_NTF';
                case self::WINK_SEND_REQ:
                    return 'WINK_SEND_REQ';
                case self::WINK_SEND_CFM:
                    return 'WINK_SEND_CFM';
                case self::WINK_SEND_NTF:
                    return 'WINK_SEND_NTF';
                case self::SET_LIMITATION_REQ:
                    return 'SET_LIMITATION_REQ';
                case self::SET_LIMITATION_CFM:
                    return 'SET_LIMITATION_CFM';
                case self::GET_LIMITATION_STATUS_REQ:
                    return 'GET_LIMITATION_STATUS_REQ';
                case self::GET_LIMITATION_STATUS_CFM:
                    return 'GET_LIMITATION_STATUS_CFM';
                case self::LIMITATION_STATUS_NTF:
                    return 'LIMITATION_STATUS_NTF';
                case self::MODE_SEND_REQ:
                    return 'MODE_SEND_REQ';
                case self::MODE_SEND_CFM:
                    return 'MODE_SEND_CFM';
                case self::MODE_SEND_NTF:
                    return 'MODE_SEND_NTF';
                case self::INITIALIZE_SCENE_REQ:
                    return 'INITIALIZE_SCENE_REQ';
                case self::INITIALIZE_SCENE_CFM:
                    return 'INITIALIZE_SCENE_CFM';
                case self::INITIALIZE_SCENE_NTF:
                    return 'INITIALIZE_SCENE_NTF';
                case self::INITIALIZE_SCENE_CANCEL_REQ:
                    return 'INITIALIZE_SCENE_CANCEL_REQ';
                case self::INITIALIZE_SCENE_CANCEL_CFM:
                    return 'INITIALIZE_SCENE_CANCEL_CFM';
                case self::RECORD_SCENE_REQ:
                    return 'RECORD_SCENE_REQ';
                case self::RECORD_SCENE_CFM:
                    return 'RECORD_SCENE_CFM';
                case self::RECORD_SCENE_NTF:
                    return 'RECORD_SCENE_NTF';
                case self::DELETE_SCENE_REQ:
                    return 'DELETE_SCENE_REQ';
                case self::DELETE_SCENE_CFM:
                    return 'DELETE_SCENE_CFM';
                case self::RENAME_SCENE_REQ:
                    return 'RENAME_SCENE_REQ';
                case self::RENAME_SCENE_CFM:
                    return 'RENAME_SCENE_CFM';
                case self::GET_SCENE_LIST_REQ:
                    return 'GET_SCENE_LIST_REQ';
                case self::GET_SCENE_LIST_CFM:
                    return 'GET_SCENE_LIST_CFM';
                case self::GET_SCENE_LIST_NTF:
                    return 'GET_SCENE_LIST_NTF';
                case self::GET_SCENE_INFORMATION_REQ:
                    return 'GET_SCENE_INFORMATION_REQ';
                case self::GET_SCENE_INFORMATION_CFM:
                    return 'GET_SCENE_INFORMATION_CFM';
                case self::GET_SCENE_INFORMATION_NTF:
                    return 'GET_SCENE_INFORMATION_NTF';
                case self::ACTIVATE_SCENE_REQ:
                    return 'ACTIVATE_SCENE_REQ';
                case self::ACTIVATE_SCENE_CFM:
                    return 'ACTIVATE_SCENE_CFM';
                case self::STOP_SCENE_REQ:
                    return 'STOP_SCENE_REQ';
                case self::STOP_SCENE_CFM:
                    return 'STOP_SCENE_CFM';
                case self::SCENE_INFORMATION_CHANGED_NTF:
                    return 'SCENE_INFORMATION_CHANGED_NTF';
                case self::ACTIVATE_PRODUCTGROUP_REQ:
                    return 'ACTIVATE_PRODUCTGROUP_REQ';
                case self::ACTIVATE_PRODUCTGROUP_CFM:
                    return 'ACTIVATE_PRODUCTGROUP_CFM';
                case self::ACTIVATE_PRODUCTGROUP_NTF:
                    return 'ACTIVATE_PRODUCTGROUP_NTF';
                case self::GET_CONTACT_INPUT_LINK_LIST_REQ:
                    return 'GET_CONTACT_INPUT_LINK_LIST_REQ';
                case self::GET_CONTACT_INPUT_LINK_LIST_CFM:
                    return 'GET_CONTACT_INPUT_LINK_LIST_CFM';
                case self::SET_CONTACT_INPUT_LINK_REQ:
                    return 'SET_CONTACT_INPUT_LINK_REQ';
                case self::SET_CONTACT_INPUT_LINK_CFM:
                    return 'SET_CONTACT_INPUT_LINK_CFM';
                case self::REMOVE_CONTACT_INPUT_LINK_REQ:
                    return 'REMOVE_CONTACT_INPUT_LINK_REQ';
                case self::REMOVE_CONTACT_INPUT_LINK_CFM:
                    return 'REMOVE_CONTACT_INPUT_LINK_CFM';
                case self::GET_ACTIVATION_LOG_HEADER_REQ:
                    return 'GET_ACTIVATION_LOG_HEADER_REQ';
                case self::GET_ACTIVATION_LOG_HEADER_CFM:
                    return 'GET_ACTIVATION_LOG_HEADER_CFM';
                case self::CLEAR_ACTIVATION_LOG_REQ:
                    return 'CLEAR_ACTIVATION_LOG_REQ';
                case self::CLEAR_ACTIVATION_LOG_CFM:
                    return 'CLEAR_ACTIVATION_LOG_CFM';
                case self::GET_ACTIVATION_LOG_LINE_REQ:
                    return 'GET_ACTIVATION_LOG_LINE_REQ';
                case self::GET_ACTIVATION_LOG_LINE_CFM:
                    return 'GET_ACTIVATION_LOG_LINE_CFM';
                case self::ACTIVATION_LOG_UPDATED_NTF:
                    return 'ACTIVATION_LOG_UPDATED_NTF';
                case self::GET_MULTIPLE_ACTIVATION_LOG_LINES_REQ:
                    return 'GET_MULTIPLE_ACTIVATION_LOG_LINES_REQ';
                case self::GET_MULTIPLE_ACTIVATION_LOG_LINES_NTF:
                    return 'GET_MULTIPLE_ACTIVATION_LOG_LINES_NTF';
                case self::GET_MULTIPLE_ACTIVATION_LOG_LINES_CFM:
                    return 'GET_MULTIPLE_ACTIVATION_LOG_LINES_CFM';
                case self::SET_UTC_REQ:
                    return 'SET_UTC_REQ';
                case self::SET_UTC_CFM:
                    return 'SET_UTC_CFM';
                case self::RTC_SET_TIME_ZONE_REQ:
                    return 'RTC_SET_TIME_ZONE_REQ';
                case self::RTC_SET_TIME_ZONE_CFM:
                    return 'RTC_SET_TIME_ZONE_CFM';
                case self::GET_LOCAL_TIME_REQ:
                    return 'GET_LOCAL_TIME_REQ';
                case self::GET_LOCAL_TIME_CFM:
                    return 'GET_LOCAL_TIME_CFM';
                case self::PASSWORD_ENTER_REQ:
                    return 'PASSWORD_ENTER_REQ';
                case self::PASSWORD_ENTER_CFM:
                    return 'PASSWORD_ENTER_CFM';
                case self::PASSWORD_CHANGE_REQ:
                    return 'PASSWORD_CHANGE_REQ';
                case self::PASSWORD_CHANGE_CFM:
                    return 'PASSWORD_CHANGE_CFM';
                case self::PASSWORD_CHANGE_NTF:
                    return 'PASSWORD_CHANGE_NTF';
                case self::IGNORE_2120:
                    return 'IGNORE_2120';
                case self::IGNORE_2121:
                    return 'IGNORE_2121';
                case self::IGNORE_2122:
                    return 'IGNORE_2122';
                case self::IGNORE_2123:
                    return 'IGNORE_2123';
                case self::IGNORE_2124:
                    return 'IGNORE_2124';
            }
        }
    }

    class Node
    {
        public static $SubType = [
            0x0040 => 'Interior Venetian Blind',
            0x0080 => 'Roller Shutter',
            0x0081 => 'Adjustable slats rolling shutter',
            0x0082 => 'Roller Shutter With projection',
            0x00C0 => 'Vertical Exterior Awning',
            0x0100 => 'Window opener',
            0x0101 => 'Window opener with integrated rain sensor',
            0x0140 => 'Garage door opener',
            0x017A => 'Garage door opener',
            0x0180 => 'Light',
            0x01BA => 'Light only supporting on/off',
            0x01C0 => 'Gate opener',
            0x01FA => 'Gate opener',
            0x0200 => 'Rolling Door Opener',
            0x0240 => 'Door lock',
            0x0241 => 'Window lock',
            0x0280 => 'Vertical Interior Blinds',
            0x0300 => 'Beacon',
            0x0340 => 'Dual Roller Shutter',
            0x0380 => 'Heating Temperature Interface',
            0x03C0 => 'On/Off switch',
            0x0400 => 'Horizontal awning',
            0x0440 => 'Exterior Venetian blind',
            0x0480 => 'Louver blind',
            0x04C0 => 'Curtain track',
            0x0500 => 'Ventilation point',
            0x0501 => 'Air inlet',
            0x0502 => 'Air transfer',
            0x0503 => 'Air outlet',
            0x0540 => 'Exterior heating',
            0x057A => 'Exterior heating',
            0x0580 => 'Heat pump',
            0x05C0 => 'Intrusion alarm',
            0x0600 => 'Swinging Shutters',
            0x0601 => 'Swinging Shutter with independent handling of the leaves'
        ];
    }
}

namespace KLF200\Node{
    class Property
    {
        public const NodeId = 'NodeId';
    }

    class Attribute
    {
        public const NodeSubType = 'NodeSubType';
    }
}

namespace KLF200\Gateway{
    class Property
    {
        public const Password = 'Password';
        public const RebootOnShutdown = 'RebootOnShutdown';
    }

    class Attribute
    {
        public const ClientSocketStateOnShutdown = 'ClientSocketStateOnShutdown';
    }

    class Timer
    {
        public const KeepAlive = 'KeepAlive';
    }
}

namespace KLF200\ClientSocket{
    class Property
    {
        public const Open = 'Open';
        public const Host = 'Host';
        public const Port = 'Port';
        public const UseSSL = 'UseSSL';
        public const VerifyPeer = 'VerifyPeer';
        public const VerifyHost = 'VerifyHost';
    }
}