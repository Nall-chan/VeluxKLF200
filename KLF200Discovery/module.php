<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/KLF200Class.php';  // diverse Klassen
eval('declare(strict_types=1);namespace KLF200Discovery {?>' . file_get_contents(__DIR__ . '/../libs/helper/DebugHelper.php') . '}');

/**
 * KLF200Discovery Klasse
 * Erweitert IPSModule.
 *
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2024 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 *
 * @version       1.00
 *
 * @method bool SendDebug(string $Message, mixed $Data, int $Format)
 */
class KLF200Discovery extends IPSModule
{
    use \KLF200Discovery\DebugHelper;

    public function GetConfigurationForm(): string
    {
        $Form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        if ($this->GetStatus() == IS_CREATING) {
            return json_encode($Form);
        }
        $Form['actions'][0]['values'] = $this->GetDevices();
        $this->SendDebug('FORM', json_encode($Form), 0);
        $this->SendDebug('FORM', json_last_error_msg(), 0);

        return json_encode($Form);
    }

    private function GetDevices(): array
    {
        $Gateways = $this->GetKLFs();
        $this->SendDebug('Gateways', $Gateways, 0);
        $IPSDevices = $this->GetIPSInstances();
        $this->SendDebug('IPS Devices', $IPSDevices, 0);
        $Values = [];
        foreach ($Gateways as $Device) {
            $InstanceID = false;
            $Host = false;
            foreach ($Device['host'] as $DeviceHost) {
                $InstanceID = array_search(strtolower($DeviceHost), $IPSDevices);
                if ($InstanceID) {
                    $Host = $DeviceHost;
                    break;
                }
            }
            if (!$Host) {
                $Host = array_shift($Device['host']);
            }
            $Values[] = [
                'host'               => $Host,
                'name'               => ($InstanceID ? IPS_GetName($InstanceID) : $Device['name']),
                'instanceID'         => ($InstanceID ? $InstanceID : 0),
                'create'             => [
                    [
                        'moduleID'         => \KLF200\GUID::Configurator,
                        'configuration'    => new stdClass()
                    ],
                    [
                        'moduleID'         => \KLF200\GUID::Gateway,
                        'configuration'    => [
                            \KLF200\Gateway\Property::Password=> 'velux123'
                        ]
                    ],
                    [
                        'moduleID'         => \KLF200\GUID::ClientSocket,
                        'configuration'    => [
                            \KLF200\ClientSocket\Property::Open       => true,
                            \KLF200\ClientSocket\Property::Host       => $Host,
                            \KLF200\ClientSocket\Property::Port       => 51200,
                            \KLF200\ClientSocket\Property::UseSSL     => true,
                            \KLF200\ClientSocket\Property::VerifyPeer => false,
                            \KLF200\ClientSocket\Property::VerifyHost => true
                        ]
                    ]
                ]
            ];
            if ($InstanceID !== false) {
                unset($IPSDevices[$InstanceID]);
            }
        }
        foreach ($IPSDevices as $InstanceID => $Host) {
            $Values[] = [
                'host'               => $Host,
                'name'               => IPS_GetName($InstanceID),
                'instanceID'         => $InstanceID,
            ];
        }
        return $Values;
    }

    private function GetKLFs(): array
    {
        $mDNSInstanceIDs = IPS_GetInstanceListByModuleID(\KLF200\GUID::DDNS);
        $resultServiceTypes = ZC_QueryServiceType($mDNSInstanceIDs[0], '_http._tcp', '');
        if (!$resultServiceTypes) {
            die;
        }
        $this->SendDebug('mDNS resultServiceTypes', $resultServiceTypes, 0);
        $KLFs = [];
        foreach ($resultServiceTypes as $device) {
            if (strpos($device['Name'], 'VELUX_KLF_LAN_') === false) {
                continue;
            }
            $KLF = [];
            $deviceInfo = ZC_QueryService($mDNSInstanceIDs[0], $device['Name'], '_http._tcp', 'local.');
            $this->SendDebug('mDNS QueryService', $device['Name'] . ' ' . $device['Type'] . ' ' . $device['Domain'] . '.', 0);
            $this->SendDebug('mDNS QueryService Result', $deviceInfo, 0);
            if (empty($deviceInfo)) {
                continue;
            }
            if (empty($deviceInfo[0]['IPv4'])) { //IPv4 und IPv6 sind vertauscht
                $KLF['IPv4'] = $deviceInfo[0]['IPv6'];
            } else {
                $KLF['IPv4'] = $deviceInfo[0]['IPv4'];
                if (isset($deviceInfo[0]['IPv6'])) {
                    foreach ($deviceInfo[0]['IPv6'] as $Index => $ipv6) {
                        $KLF['IPv6'][] = '[' . $ipv6 . ']';
                        $Hostname = gethostbyaddr($ipv6);
                        if ($Hostname != $ipv6) {
                            $KLF['Hostname'][$Index] = $Hostname;
                        }
                        $KLF['Hostname'][20 + $Index] = '[' . $ipv6 . ']';
                    }
                }
            }
            foreach ($KLF['IPv4'] as $Index => $ipv4) {
                $Hostname = gethostbyaddr($ipv4);
                if ($Hostname != $ipv4) {
                    $KLF['Hostname'][10 + $Index] = $Hostname;
                }
                $KLF['Hostname'][30 + $Index] = $ipv4;
            }
            ksort($KLF['Hostname']);
            array_push($KLFs, ['name' => $device['Name'], 'host'=>$KLF['Hostname']]);
        }
        return $KLFs;
    }

    private function GetIPSInstances(): array
    {
        $InstanceIDList = IPS_GetInstanceListByModuleID(\KLF200\GUID::Configurator);
        $Devices = [];
        foreach ($InstanceIDList as $InstanceID) {
            $SplitterID = IPS_GetInstance($InstanceID)['ConnectionID'];
            if ($SplitterID > 0) {
                $IO = IPS_GetInstance($SplitterID)['ConnectionID'];
                if ($IO > 0) {
                    $parentGUID = IPS_GetInstance($IO)['ModuleInfo']['ModuleID'];
                    if ($parentGUID == \KLF200\GUID::ClientSocket) {
                        $Devices[$InstanceID] = strtolower(IPS_GetProperty($IO, \KLF200\ClientSocket\Property::Host));
                    }
                }
            }
        }
        return $Devices;
    }
}
