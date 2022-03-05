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

        try {
            userSessionLogin($this->ReadPropertyString("ip"), $this->ReadPropertyString("user"), $this->ReadPropertyString("password"));
        } catch (ErrorException $e) {
        }
    }

    public function RequestAction($Ident, $Value) {
        // TODO: change on var
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
                        "name" => "name"
                    ],
                    [
                        "caption" => "Type",
                        "name" => "type"
                    ],
                    [
                        "caption" => "Model",
                        "name" => "model"
                    ],
                    [
                        "caption" => "Firmware",
                        "name" => "firmVer"
                    ],
                    [
                        "caption" => "Hardware Version",
                        "name" => "hardVer"
                    ],
                    [
                        "caption" => "Disk Number",
                        "name" => "diskNum"
                    ],
                    [
                        "caption" => "Wifi Connection",
                        "name" => "wifi"
                    ]
                ],
                "values" => [
                    
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
                'caption' => 'HomeConnect Dishwasher created.',
            ],
            [
                'code'    => 104,
                'icon'    => 'inactive',
                'caption' => 'interface closed.',
            ]
        ];
    }
}

?>