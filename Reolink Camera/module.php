<?php

// needed for token access to the device
require_once(dirname(__FILE__, 2) . "/libs/accessHandler.php");

class ReolinkCamera extends IPSModule {

    // overwrite default create function
    public function Create() {
        parent::Create();

        // Needed values from the user
        $this->RegisterPropertyString("user", "admin");
        $this->RegisterPropertyString("password", "");
        $this->RegisterPropertyString("ip", "0.0.0.0");
    }

    // called on changes
    public function ApplyChanges() {
        parent::ApplyChanges();

        //TODO: implement in Token getter
    }

    public function RequestAction($Ident, $Value) {
        // TODO: change on var
    }

    public function GetDevInfo() {
        // request command on device
        $rsp = $this->deviceCall("GetDevInfo", 0, []);
        if (isset($rsp)) return $rsp["value"]["DevInfo"];
        return [];
    }


    /** A function that simplifies the call of a command
     * @param string $cmd command that should be executed on the device
     * @param int $action range,value,init (1) or simple return (0)
     * @param array $param Params that are needed for the command
     * @return array|void Result from the API of the device
     */
    protected function deviceCall(string $cmd, int $action, array $param) {
        // needed
        $ip = $this->ReadPropertyString("ip");
        $username = $this->ReadPropertyString("user");
        $password = $this->ReadPropertyString("password");

        // try to reach the device and call command with token
        try {
            $token = getToken($ip, $username, $password);
            $response = cmd($ip, $token, $cmd, $action, $param);
            // normale response, set status to normal 102
            $this->SetStatus(102);
            // return result
            return $response;
        } catch (ErrorException $exception) {
            // catch an error from the api
            $this->SetStatus($exception->getCode());
        }
    }

    //============================================================ FORM.JSON

    /** This Function will return the IP Symcon needed form.json
     * @return false|string Form json
     */
    public function GetConfigurationForm() {
        // return current form
        $Form = json_encode([
            'elements' => $this->FormElements(),
            'actions'  => $this->FormActions(),
            'status'   => $this->FormStatus(),
        ]);
        $this->SendDebug('FORM', $Form, 0);
        $this->SendDebug('FORM', json_last_error_msg(), 0);

        return $Form;
    }

    // action table
    protected function FormActions() {
        return[];
    }

    // elements
    protected function FormElements() {
        return[
            [
                "type" => "RowLayout",
                "items" => [
                    [
                        "type" => "ValidationTextBox",
                        "name" => "user",
                        "caption" => "Username"
                    ],
                    [
                        "type" => "PasswordTextBox",
                        "name" => "password",
                        "caption" => "Password"
                    ],
                    [
                        "type" => "ValidationTextBox",
                        "name" => "ip",
                        "caption" => "IP-Address"
                    ]
                ]
            ],
            [
                "type" => "List",
                "name" => "devInfo",
                "caption" => "Information about this device",
                "rowCount" => 1,
                "add" => false,
                "delete" => false,
                "columns" => [
                    [
                        "caption" => "Name",
                        "name" => "name",
                        "width" => "auto"
                    ],
                    [
                        "caption" => "Type",
                        "name" => "type",
                        "width" => "100px"
                    ],
                    [
                        "caption" => "Model",
                        "name" => "model",
                        "width" => "130px"
                    ],
                    [
                        "caption" => "Firmware",
                        "name" => "firmVer",
                        "width" => "130px"
                    ],
                    [
                        "caption" => "Hardware Version",
                        "name" => "hardVer",
                        "width" => "150px"
                    ],
                    [
                        "caption" => "Disk Number",
                        "name" => "diskNum",
                        "width" => "100px"
                    ],
                    [
                        "caption" => "Connected via Wifi",
                        "name" => "wifi",
                        "width" => "150px"
                    ]
                ],
                "values" => [
                    $this->GetDevInfo()
                ],
            ]
        ];
    }

    // status codes
    protected function FormStatus() {
        return [
            [
                'code'    => 101,
                'icon'    => 'inactive',
                'caption' => 'Creating instance.',
            ],
            [
                'code'    => 102,
                'icon'    => 'active',
                'caption' => 'Reolink Camera created.',
            ],
            [
                'code'    => 104,
                'icon'    => 'inactive',
                'caption' => 'interface closed.',
            ],
            [
                'code'    => 200,
                'icon'    => 'error',
                'caption' => "Can't reach device with the given IP",
            ],
            [
                'code'    => 1000,
                'icon'    => 'error',
                'caption' => 'Missing parameters',
            ],
            [
                'code'    => 2000,
                'icon'    => 'inactive',
                'caption' => 'Used up memory',
            ],
            [
                'code'    => 3000,
                'icon'    => 'error',
                'caption' => 'check error',
            ],
            [
                'code'    => 4000,
                'icon'    => 'error',
                'caption' => 'Parameters error',
            ],
            [
                'code'    => 5000,
                'icon'    => 'inactive',
                'caption' => 'Reached the max session number',
            ],
            [
                'code'    => 6000,
                'icon'    => 'inactive',
                'caption' => 'Login required',
            ],
            [
                'code'    => 7000,
                'icon'    => 'inactive',
                'caption' => 'Login error',
            ],
            [
                'code'    => 8000,
                'icon'    => 'inactive',
                'caption' => 'Operation timeout',
            ],
            [
                'code'    => 9000,
                'icon'    => 'inactive',
                'caption' => 'Not supported',
            ],
            [
                'code'    => 10000,
                'icon'    => 'error',
                'caption' => 'Protocol error',
            ],
        ];
    }
}

?>