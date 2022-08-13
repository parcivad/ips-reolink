<?php

// include api access
require_once(dirname(__FILE__, 2) . '/libs/api.php');

class ReolinkCamera extends IPSModule {

    //============================================================================================== IP-SYMCON FUNCTIONS

    // overwrite default create function
    public function Create() {
        parent::Create();

        // important camera data
        $this->RegisterPropertyString("username", "admin");
        $this->RegisterPropertyString("password", "");
        $this->RegisterPropertyString("ip", "0.0.0.0");

        // configurations
        $this->RegisterPropertyBoolean("log", true);

        // save session information as json in string
        $this->RegisterAttributeString("usedIP", null);
        $this->RegisterAttributeString("token", null);
        $this->RegisterAttributeInteger("leaseTime", null);
        $this->RegisterAttributeInteger("timestamp", null);
    }

    // called on changes
    public function ApplyChanges() {
        parent::ApplyChanges();

        //TODO: implement in Token getter
    }

    // when destroyed disconnect from camera
    public function Destroy() {
        parent::Destroy();
        // logout
        try {
            $this->userSessionLogout($this->ReadPropertyString("ip"));
        } catch (Exception $exception) {
            echo "Couldn't log out! (User will be kicked out from the camera)";
        }
    }

    public function RequestAction($Ident, $Value) {
        // TODO: change on var
    }

    //============================================================================= REOLINK GETTER AND SETTER

    public function GetAbility(): array {
        // request command on device
        $rsp = $this->cmd("GetAbility", 0, ["User" => ["userName" => $this->ReadPropertyString("username")]]);
        if (!isset($rsp)) return [];
        // if not empty return
        return $rsp["value"]["Ability"];
    }

    /**
     * Get Device Info
     * @return array|mixed list of device information
     */
    public function GetDevInfo(): array {
        // request command on device
        $rsp = $this->cmd("GetDevInfo", 0, []);
        if (!isset($rsp)) return [];
        // if not empty return
        $rsp["value"]["DevInfo"]["wifi"] = boolval($rsp["value"]["DevInfo"]["wifi"]);
        return $rsp["value"]["DevInfo"];
    }

    /**
     * Get Device Name
     * @return string|null device name
     */
    public function GetDevName(): ?string {
        // request command on device
        $rsp = $this->cmd("GetDevName", 0, []);
        if (!isset($rsp)) return null;
        // if not empty return
        return $rsp["value"]["DevName"]["name"];
    }

    /**
     * Set Device Name
     * @param string $name new device name
     */
    public function SetDevName(string $name) {
        // request command on device
        $this->cmd("SetDevName", 0, ["DevName" => ["name" => $name]]);
    }

    /**
     * Get Device Time
     * @return array|null device name
     */
    public function GetTime(): ?array {
        // request command on device
        $rsp = $this->cmd("GetTime", 0, []);
        if (!isset($rsp)) return null;
        // if not empty return
        return $rsp["value"]["Time"];
    }

    /**
     * Set Device Time
     */
    public function SetTime(int $year, int $month, int $day, int $hour, int $minute, int $second,
                            string $timeFmt, int $timeZone, int $hourFmt): void {
        // request command on device
        $rsp = $this->cmd("GetTime", 0, []);
        if (!isset($rsp)) return;
        // if not empty return
        $DstSettings = $rsp["value"]["Dst"];

        // request command on device
        $this->cmd("SetTime", 0,
            [
                "Dst" => $DstSettings,
                "Time" => [
                    "day" => $day,
                    "hour" => $hour,
                    "min" => $minute,
                    "mon" => $month,
                    "sec" => $second,
                    "timeFmt" => $timeFmt,
                    "timeZone" => $timeZone,
                    "year" => $year,
                    "hourFmt" => $hourFmt
                ]
            ]);
    }

    /**
     * Get Device Time Shift
     * @return array|null device name
     */
    public function GetTimeShift(): ?array {
        // request command on device
        $rsp = $this->cmd("GetTime", 0, []);
        if (!isset($rsp)) return null;
        // if not empty return
        $rsp["value"]["Dst"]["enable"] = boolval($rsp["value"]["Dst"]["enable"]);
        return $rsp["value"]["Dst"];
    }

    /**
     * Set Device Time Shift
     */
    public function SetTimeShift(bool $enable, int $startWeekday, int $startWeek, int $startMonth, int $startHour,
                                 int $startMinute, int $startSecond, int $offset, int $endWeekday, int $endWeek,
                                 int $endMonth, int $endHour, int $endMinute, int $endSecond): void {
        // request command on device
        $rsp = $this->cmd("GetTime", 0, []);
        if (!isset($rsp)) return;
        // if not empty return
        $TimeSettings = $rsp["value"]["Time"];

        // request command on device
        $this->cmd("SetTime", 0,
            [
                "Dst" => [
                    "enable" => intval($enable),
                    "endHour" => $endHour,
                    "endMin" => $endMinute,
                    "endMon" => $endMonth,
                    "endSec" => $endSecond,
                    "endWeek" => $endWeek,
                    "endWeekday" => $endWeekday,
                    "offset" => $offset,
                    "startHour" => $startHour,
                    "startMin" => $startMinute,
                    "startMon" => $startMonth,
                    "startSec" => $startSecond,
                    "startWeek" => $startWeek,
                    "startWeekday" => $startWeekday
                ],
                "Time" => $TimeSettings
            ]);
    }

    /**
     * Get Device HDD info
     * @return array|null device name
     */
    public function GetHDDInfo(): ?array {
        // request command on device
        $rsp = $this->cmd("GetHddInfo", 0, []);
        if (!isset($rsp)) return null;
        // if not empty return
        return [
            "capacity" => $rsp["value"]["HddInfo"][0]["capacity"],
            "remainingCapacity" => $rsp["value"]["HddInfo"][0]["size"],
            "formatted" => boolval($rsp["value"]["HddInfo"][0]["format"]),
            "mounted" => boolval($rsp["value"]["HddInfo"][0]["mount"]),
            "number" => $rsp["value"]["HddInfo"][0]["number"],
            "storageType" => $rsp["value"]["HddInfo"][0]["storageType"]
        ];
    }

    /**
     * Get Device Channel
     * @return array|null Channel Status
     */
    public function GetChannelStatus(): ?array {
        // request command on device
        $rsp = $this->cmd("GetChannelstatus", 0, []);
        if (!isset($rsp)) return null;
        // if not empty return
        for ($i=0; $i < count($rsp["value"]["status"]); $i++) {
            $rsp["value"]["status"][$i]["online"] = boolval($rsp["value"]["status"][$i]["online"]);
        }
        return $rsp["value"];
    }

    /**
     * Get Device Name
     * @return array|null device name
     */
    public function GetOnline(): ?array {
        // request command on device
        $rsp = $this->cmd("GetOnline", 0, []);
        if (!isset($rsp)) return null;
        // if not empty return
        for ($i=0; $i < count($rsp["value"]["User"]); $i++) {
            $rsp["value"]["User"][$i]["canbeDisconn"] = boolval($rsp["value"]["User"][$i]["canbeDisconn"]);
        }
        return $rsp["value"]["User"];
    }

    /**
     * Disconnect an online user
     * @param string $userName  Name of the user
     * @throws ErrorException   When no user is found
     */
    public function DisconnectUser(string $userName): void {
        // request command on device
        $rsp = $this->cmd("GetOnline", 0, []);
        if (!isset($rsp)) return;
        // if not empty return
        foreach ($rsp["value"]["User"] as $user) {
            if ($user["userName"] == $userName) {
                // disconnect this user
                $this->cmd("Disconnect", 0, [
                    "User" => [
                        "userName" => $userName,
                        "sessionId" => $user["sessionId"]
                    ]
                ]);
                return;
            }
        }
        // no user found
        throw new ErrorException("Couldn't find a user with the name ". $userName);
    }

    /**
     * Get Device Image Configuration
     * @param int $channel  Channel of the Device
     * @return array|null Image Configuration
     */
    public function GetImageConfiguration(int $channel): ?array {
        // request command on device
        $rsp = $this->cmd("GetImage", 0, ["channel" => $channel]);
        if (!isset($rsp)) return null;
        // if not empty return
        return $rsp["value"]["Image"];
    }

    /**
     * Set Device Image Configuration
     */
    public function SetImageConfiguration() {

    }





    //============================================================================================== SESSION HANDLER

    /*** Function that makes a device call simple by using api curl file
     * @param string $cmd Command that should be executed
     * @param string $action Action type 1/0
     * @param array $param Parameters of the function
     * @return array|null
     */
    protected function cmd(string $cmd, string $action, array $param) {
        $this->_log("starting API call, command: " . $cmd, KL_MESSAGE);
        // try to request on device
        try {
            // request
            $ip = $this->ReadPropertyString("ip");
            $token = $this->getToken();
            $this->SetStatus(102);
            return cmd($ip, $token, $cmd, $action, $param);
        } catch (ErrorException $exception) {
            // session reset
            if ($exception->getCode() == 201) {
                $this->WriteAttributeString("usedIP", null);
                $this->WriteAttributeString("token", null);
                $this->WriteAttributeInteger("leaseTime", null);
                $this->WriteAttributeInteger("timestamp", null);
            };
            // module status
            $this->SetStatus($exception->getCode());
            return null;
        }
    }

    /** Function that should present a working token for the targeted device
     * @return string Token
     * @throws ErrorException
     */
    protected function getToken(): string {
        // token elapsed
        if ($this->ReadAttributeString("token") != null
            && time() - $this->ReadAttributeInteger("timestamp") < $this->ReadAttributeInteger("leaseTime")
            && $this->ReadAttributeString("usedIP") == $this->ReadPropertyString("ip") )
            return $this->ReadAttributeString("token");

        $this->_log("trying to refresh/get a new token", KL_NOTIFY);

        // try to refresh the token
        try {
            // check device change
            if ($this->ReadAttributeString("usedIP") != null
                && $this->ReadAttributeString("usedIP") != $this->ReadPropertyString("ip")) {
                // logout old session
                $this->userSessionLogout($this->ReadAttributeString("usedIP"));
            } else {
                // default logout of current ip
                $this->userSessionLogout($this->ReadPropertyString("ip"));
            }
            // wait for the device to logout
            sleep(1);
            $this->SetStatus(102);
            return $this->userSessionLogin();
        } catch (ErrorException $exception) {
            // session reset
            if ($exception->getCode() == 201) {
                $this->WriteAttributeString("usedIP", "");
                $this->WriteAttributeString("token", "");
                $this->WriteAttributeInteger("leaseTime", 0);
                $this->WriteAttributeInteger("timestamp", 0);
            };
            // module status
            $this->SetStatus($exception->getCode());
            return "";
        }
    }

    /*** Function to retrieve the
     * @param string $ip IP-Address of the device
     * @return void
     * @throws ErrorException
     */
    protected function userSessionLogout(string $ip) {
        // check if token that should be logged out is present
        if ($this->ReadAttributeString("token") == null) return;

        // send command to device
        cmd($ip, $this->ReadAttributeString("token"), "Logout", 0, []);

        $this->_log("Active user should be logged out", KL_MESSAGE);

        // clear and save
        $this->WriteAttributeString("usedIP", null);
        $this->WriteAttributeString("token", null);
        $this->WriteAttributeInteger("leaseTime", null);
        $this->WriteAttributeInteger("timestamp", null);
    }

    /*** Function to retrieve the
     * @param string $ip IP-Address of the device
     * @return string returns a token
     * @throws ErrorException
     */
    protected function userSessionLogin() {
        $param = [
            "User" => [
                "userName" => $this->ReadPropertyString("username"),
                "password" => $this->ReadPropertyString("password")
            ]
        ];

        // send command to device
        $ip = $this->ReadPropertyString("ip");
        $rsp = cmd($ip, "", "Login", 0, $param);

        $this->WriteAttributeString("usedIP", $ip);
        $this->WriteAttributeString("token", $rsp["value"]["Token"]["name"]);
        $this->WriteAttributeInteger("leaseTime", $rsp["value"]["Token"]["leaseTime"]);
        $this->WriteAttributeInteger("timestamp", time());
        // save
        $this->_log("User " . $this->ReadPropertyString("username") . " should be logged in", KL_MESSAGE);
        return $rsp["value"]["Token"]["name"];
    }

    //============================================================================================== DEBUG FUNCTIONS

    /** Function to debug
     * @param string $msg Message
     * @param int $format Format Typ
     * @return void
     */
    protected function _log(string $msg, int $format) {
        if (!$this->ReadPropertyBoolean("log")) return;
        $this->LogMessage($msg, $format);
    }

    //============================================================================================== FORM.JSON

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
                        "name" => "username",
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
                    ],
                    [
                        "type" => "Image",
                        "image" => "data:image/png;base64, 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"
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
                        "width" => "170px"
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
            ],
            [
                "type" => "CheckBox",
                "name" => "log",
                "caption" => "Log"
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