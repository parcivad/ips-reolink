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

        $this->RegisterPropertyInteger("scheduledCommon", 10);
        $this->RegisterPropertyInteger("scheduledImportant", 5);
        $this->RegisterPropertyBoolean("liveCameraView", true);
        $this->RegisterPropertyBoolean("imageGrabber", true);
        $this->RegisterPropertyBoolean("saveImageOnMotion", true);
        $this->RegisterPropertyInteger("imageGrabberRate", 10);
        $this->RegisterPropertyBoolean("liveStream", false);
        $this->RegisterPropertyInteger("streamType", 0);

        // configurations
        $this->RegisterPropertyBoolean("log", true);

        // save session information as json in string
        $this->RegisterAttributeString("usedIP", null);
        $this->RegisterAttributeString("token", null);
        $this->RegisterAttributeInteger("leaseTime", null);
        $this->RegisterAttributeInteger("timestamp", null);

        // register Profiles and Variables
        $this->registerProfiles();
        $this->registerVariables();

        // Scheduled Functions
        $this->RegisterTimer('scheduledCommon', 600000, "REOLINK_refreshCommonVariables( $this->InstanceID );");
        $this->RegisterTimer('scheduledImportant', 5000, "REOLINK_refreshImportantVariables( $this->InstanceID );");
        $this->RegisterTimer('imageGrabberRate', 10000, "REOLINK_UpdateSnapshot( $this->InstanceID );");
    }

    // called on changes
    public function ApplyChanges() {
        parent::ApplyChanges();

        // renew time
        $this->SetTimerInterval("scheduledCommon", $this->ReadPropertyInteger("scheduledCommon") * 60000);
        $this->SetTimerInterval("scheduledImportant", $this->ReadPropertyInteger("scheduledCommon") * 1000);
        if ($this->ReadPropertyBoolean("imageGrabber")) {
            $this->SetTimerInterval("imageGrabberRate", $this->ReadPropertyInteger("imageGrabberRate") * 1000);
        } else {
            $this->SetTimerInterval("imageGrabberRate", 0);
        }

        // call function
        $this->refreshCommonVariables();
        $this->UpdateSnapshot();
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
        // decide action
        switch ($Ident) {
            case "bright":
            case "contrast":
            case "saturation":
            case "hue":
            case "sharpen":
                $this->SetImageConfiguration(
                    $this->GetValue("bright"),
                    $this->GetValue("contrast"),
                    $this->GetValue("saturation"),
                    $this->GetValue("hue"),
                    $this->GetValue("sharpen"),
                );
                $this->SetValue($Ident, $Value);
                break;

            case "time_format":
                $this->SetTimeFormat($Value);
                $this->SetValue($Ident, $Value);
                break;

            case "hour_format":
                $this->SetValue($Ident, $Value);
                break;
        }
        // update image
        $this->UpdateSnapshot();
    }

    //============================================================================= Scheduled Background Checks

    /**
     * Scheduled function to refresh some background variables of the Instanze
     * @return void
     * @throws Exception
     */
    public function refreshCommonVariables() {
        // request command on device
        $ImageSettings = $this->GetImageConfiguration();
        if (!isset($ImageSettings)) return;
        // Set Variables
        $this->SetValue("bright", $ImageSettings["bright"]);
        $this->SetValue("contrast", $ImageSettings["contrast"]);
        $this->SetValue("hue", $ImageSettings["hue"]);
        $this->SetValue("saturation", $ImageSettings["saturation"]);
        $this->SetValue("sharpen", $ImageSettings["sharpen"]);

        // next hdd
        $HDDInfo = $this->GetHDDInfo();
        if (!isset($HDDInfo)) return;
        // Set Variables
        $this->SetValue("hdd_capacity", $HDDInfo["capacity"]);
        $this->SetValue("hdd_used_capacity", $HDDInfo["capacity"]-$HDDInfo["remainingCapacity"]);
        $this->SetValue("hdd_remaining_capacity", $HDDInfo["remainingCapacity"]);
        $this->SetValue("hdd_formatted", $HDDInfo["formatted"]);
        $this->SetValue("hdd_mounted", $HDDInfo["mounted"]);

        // next name and wifi
        $DevInfo = $this->GetDevInfo();
        if (!isset($DevInfo)) return;
        // Set Variables
        $this->SetValue("name", $DevInfo["name"]);
        $this->SetValue("version", $DevInfo["firmVer"]);
        $this->SetValue("wifi", $DevInfo["wifi"]);

        // next get time settings
        $TimeSettings = $this->GetTime();
        if (!isset($DevInfo)) return;
        // Set Variables
        $this->SetValue("time_format", $TimeSettings["timeFmt"]);
        $this->SetValue("hour_format", $TimeSettings["hourFmt"]);

        // refresh Live Stream settings
        $this->UpdateLiveStream();
    }

    /**
     * Scheduled function to refresh some background variables of the Instanze
     * @return void
     * @throws Exception
     */
    public function refreshImportantVariables() {
        // request on device
        $MotionState = $this->GetMotionDetectionState();
        // if valid
        if (!isset($MotionState)) return;
        // Set Variable
        $this->SetValue("motion", $MotionState);
    }

    //============================================================================= REOLINK GETTER AND SETTER

    public function GetAbility(): array {
        // request command on device
        $rsp = $this->cmd("GetAbility", 0, ["User" => ["userName" => $this->ReadPropertyString("username")]], false);
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
        $rsp = $this->cmd("GetDevInfo", 0, [], false);
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
        $rsp = $this->cmd("GetDevName", 0, [], false);
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
        $this->cmd("SetDevName", 0, ["DevName" => ["name" => $name]], false);
    }

    /**
     * Get Device Time
     * @return array|null device name
     */
    public function GetTime(): ?array {
        // request command on device
        $rsp = $this->cmd("GetTime", 0, [], false);
        if (!isset($rsp)) return null;
        // if not empty return
        return $rsp["value"]["Time"];
    }

    /**
     * Set Device Time
     * @param int $year         Year of the Time
     * @param int $month        Month of the Time
     * @param int $day          Day of the Time
     * @param int $hour         Hour of the Time
     * @param int $minute       Minute of the Time
     * @param int $second       Second of the Time
     * @param string $timeFmt   "MM/DD/YYY", "DD/MM/YYYY"
     * @param int $timeZone     Seconds of the Timezone difference
     * @param int $hourFmt      Format 0 for 24 hours, 1 for 12 hours
     */
    public function SetTime(int $year, int $month, int $day, int $hour, int $minute, int $second,
                            string $timeFmt, int $timeZone, int $hourFmt): void {
        // request command on device
        $rsp = $this->cmd("GetTime", 0, [], false);
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
            ], false);
    }

    /**
     * Set Device Hour Format
     * @param string $timeFmt   "MM/DD/YYY", "DD/MM/YYYY"
     */
    public function SetTimeFormat(string $timeFmt): void {
        // request command on device
        $rsp = $this->cmd("GetTime", 0, [], false);
        if (!isset($rsp)) return;
        // if not empty return
        $rsp["value"]["Time"]["timeFmt"] = $timeFmt;

        // request command on device
        $this->cmd("SetTime", 0, $rsp["value"], false);
    }

    /**
     * Set Device Time Format
     * @param int $hourFmt      Format 0 for 24 hours, 1 for 12 hours
     */
    public function SetHourFormat(int $hourFmt): void {
        // request command on device
        $rsp = $this->cmd("GetTime", 0, [], false);
        if (!isset($rsp)) return;
        // if not empty return
        $rsp["value"]["Time"]["hourFmt"] = $hourFmt;

        // request command on device
        $this->cmd("SetTime", 0, $rsp["value"], false);
    }

    /**
     * Get Device Time Shift
     * @return array|null device name
     */
    public function GetTimeShift(): ?array {
        // request command on device
        $rsp = $this->cmd("GetTime", 0, [], false);
        if (!isset($rsp)) return null;
        // if not empty return
        $rsp["value"]["Dst"]["enable"] = boolval($rsp["value"]["Dst"]["enable"]);
        return $rsp["value"]["Dst"];
    }

    /**
     * Set Device Time Shift (Zeitverschiebung)
     * @param bool $enable      Feature enabled on the device
     * @param int $startWeekday Weekday of the TimeShift start
     * @param int $startWeek    Week of the TimeShift start
     * @param int $startMonth   Month of the TimeShift start
     * @param int $startHour    Hour of the TimeShift start
     * @param int $startMinute  Minute of the TimeShift start
     * @param int $startSecond  Second of the TimeShift start
     * @param int $offset       Time offset
     * @param int $endWeekday   Weekday of the TimeShift end
     * @param int $endWeek      Week of the TimeShift end
     * @param int $endMonth     Month of the TimeShift end
     * @param int $endHour      Hour of the TimeShift end
     * @param int $endMinute    Minute of the TimeShift end
     * @param int $endSecond    Second of the TimeShift end
     */
    public function SetTimeShift(bool $enable, int $startWeekday, int $startWeek, int $startMonth, int $startHour,
                                 int $startMinute, int $startSecond, int $offset, int $endWeekday, int $endWeek,
                                 int $endMonth, int $endHour, int $endMinute, int $endSecond): void {
        // request command on device
        $rsp = $this->cmd("GetTime", 0, [], false);
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
            ], false);
    }

    /**
     * Get Device HDD info
     * @return array|null device name
     */
    public function GetHDDInfo(): ?array {
        // request command on device
        $rsp = $this->cmd("GetHddInfo", 0, [], false);
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
        $rsp = $this->cmd("GetChannelstatus", 0, [], false);
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
        $rsp = $this->cmd("GetOnline", 0, [], false);
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
        $rsp = $this->cmd("GetOnline", 0, [], false);
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
                ], false);
                return;
            }
        }
        // no user found
        throw new ErrorException("Couldn't find a user with the name ". $userName);
    }

    /**
     * Get Device Image Configuration
     * @return array|null Image Configuration
     */
    public function GetImageConfiguration(): ?array {
        // request command on device
        $rsp = $this->cmd("GetImage", 0, ["channel" => 0], false);
        if (!isset($rsp)) return null;
        // if not empty return
        return $rsp["value"]["Image"];
    }

    /**
     * Set Device Image Configuration
     * @param int $bright       Bright of the Image
     * @param int $contrast     Contrast of the Image
     * @param int $saturation   Saturation of the Image
     * @param int $hue          Hue of the Image
     * @param int $sharpen      Sharpen of the Image
     */
    public function SetImageConfiguration(int $bright, int $contrast, int $saturation, int $hue, int $sharpen) {
        // request command on device
        $this->cmd("SetImage", 0,
            [
                "Image" => [
                    "channel" => 0,
                    "bright" => $bright,
                    "contrast" => $contrast,
                    "saturation" => $saturation,
                    "hue" => $hue,
                    "sharpen" => $sharpen
                ]
            ], false);
    }

    /**
     * Get Device Recordings in a certain time
     * @param int $channel          Channel that should be searched in
     * @param string $streamType    "main" or "sub" type on quality stream
     * @param int $startYear        Year of the Search Start
     * @param int $startMonth       Month of the Search Start
     * @param int $startDay         Day of the Search Start
     * @param int $startHour        Hour of the Search Start
     * @param int $startMinute      Minute of the Search Start
     * @param int $startSecond      Second of the Search Start
     * @param int $endYear          Year of the Search End
     * @param int $endMonth         Month of the Search End
     * @param int $endDay           Day of the Search End
     * @param int $endHour          Hour of the Search End
     * @param int $endMinute        Minute of the Search End
     * @param int $endSecond        Second of the Search End
     * @return array|null           Recordings
     */
    public function GetRecordings(int $channel, string $streamType, int $startYear, int $startMonth, int $startDay,
                                  int $startHour, int $startMinute, int $startSecond, int $endYear, int $endMonth,
                                  int $endDay, int $endHour, int $endMinute, int $endSecond): ?array {

        // request command on device
        $rsp = $this->cmd("Search", 1,
            [
                "Search" => [
                    "channel" => $channel,
                    "onlyStatus" => 0,
                    "streamType" => $streamType,
                    "StartTime" => [
                        "year" => $startYear,
                        "mon" => $startMonth,
                        "day" => $startDay,
                        "hour" => $startHour,
                        "min" => $startMinute,
                        "sec" => $startSecond
                    ],
                    "EndTime" => [
                        "year" => $endYear,
                        "mon" => $endMonth,
                        "day" => $endDay,
                        "hour" => $endHour,
                        "min" => $endMinute,
                        "sec" => $endSecond
                    ]
                ]
            ], false);
        if (!isset($rsp)) return null;
        // if not empty return
        return $rsp["value"]["SearchResult"];
        // request command on device
    }

    /**
     * Get Device Motion Detection State
     * @return bool|null
     * @throws Exception
     */
    public function GetMotionDetectionState(): ?bool {
        // request command on device
        $rsp = $this->cmd("GetMdState", 0, ["channel" => 0], false);
        if (!isset($rsp)) return null;
        // check if save is checked
        if ($this->ReadPropertyBoolean("saveImageOnMotion") && $rsp["value"]["state"]) {
            // request on device and get instance
            $image = $this->cmd("Snap&channel=0&rs=".base64_encode(random_bytes(10)), 0, [], true);
            if (!isset($image)) return null;
            $mediaID = IPS_GetMediaIDByName("LastMotionSnapshot", $this->InstanceID);
            // check for null
            if (!isset($mediaID)) return null;
            // set image
            IPS_SetMediaContent($mediaID, base64_encode($image));
        }

        // if not empty return
        return boolval($rsp["value"]["state"]);
    }

    /**
     * Function to get data64 coded image
     * @return void
     * @throws Exception
     */
    public function UpdateSnapshot(): void {
        // request on device and get instance
        $image = $this->cmd("Snap&channel=0&rs=".base64_encode(random_bytes(10)), 0, [], true);
        if (!isset($image)) return;
        $mediaID = IPS_GetMediaIDByName("Snapshot", $this->InstanceID);
        // check for null
        if (!isset($mediaID)) return;
        // set image
        IPS_SetMediaContent($mediaID, base64_encode($image));
    }

    /**
     * Function to get data64 coded image
     * @return void
     * @throws Exception
     */
    public function UpdateLiveStream(): void {
        // request on device and get instance
        if (!isset($image)) return;
        $mediaID = IPS_GetMediaIDByName("LiveStream", $this->InstanceID);
        // check for null
        if (!isset($mediaID)) return;

        if ($this->ReadPropertyString("liveStream")) {
            // enabled
            IPS_SetDisabled($mediaID, false);
            $streamTypeString = ["main", "sub", "ext"];
            $streamType = $this->ReadPropertyString("streamType")
            // set image
            IPS_SetMediaFile(
                $mediaID,
                "rtmp://". $this->ReadPropertyString("usedIP") ."/bcs/channel0_". $streamTypeString[$streamType].".bcs?channel=0&stream=". $streamType ."&token=". $this->getToken(),
                false);
            return;
        }
        // not active then disable instance
        IPS_SetDisabled($mediaID, true);
        IPS_SetMediaFile($mediaID, " ", false);
    }

    //============================================================================================== SESSION HANDLER

    /*** Function that makes a device call simple by using api curl file
     * @param string $cmd       Command that should be executed
     * @param string $action    Action type 1/0
     * @param array $param      Parameters of the function
     * @param bool $raw         Raw return of response
     * @return array|null
     */
    protected function cmd(string $cmd, string $action, array $param, bool $raw) {
        $this->_log("starting API call, command: " . $cmd, KL_MESSAGE);
        // try to request on device
        try {
            // request
            $ip = $this->ReadPropertyString("ip");
            $token = $this->getToken();
            $this->SetStatus(102);
            return cmd($ip, $token, $cmd, $action, $param, $raw);
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
        cmd($ip, $this->ReadAttributeString("token"), "Logout", 0, [], false);

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
        $rsp = cmd($ip, "", "Login", 0, $param, false);

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

    /**
     * register Variables for data on IP-SYmcon
     * @return void
     */
    protected function registerVariables() {
        // register Variables
        $this->RegisterVariableString("name", "Name", "", 0);
        $this->RegisterVariableString("version", "Version", "", 1);

        $this->RegisterVariableBoolean("motion", "Motion", "Motion", 4);
        $this->RegisterVariableBoolean("wifi", "Wifi", "Switch", 5);
        $this->RegisterVariableInteger("bright", "Bright", "Intensity.255", 6);
        $this->RegisterVariableInteger("contrast", "Contrast", "Intensity.255", 7);
        $this->RegisterVariableInteger("hue", "Hue", "Intensity.255", 8);
        $this->RegisterVariableInteger("saturation", "Saturation", "Intensity.255", 9);
        $this->RegisterVariableInteger("sharpen", "Sharpen", "Intensity.255", 9);

        $this->RegisterVariableString("time_format", "TIME_FORMAT", "REO_TIME_FORMAT", 10);
        $this->RegisterVariableInteger("hour_format", "HOUR_FORMAT", "REO_HOUR_FORMAT", 11);

        $this->RegisterVariableInteger("hdd_capacity", "HDD_CAPACITY", "REO_CAPACITY", 30);
        $this->RegisterVariableInteger("hdd_used_capacity", "HDD_USED_CAPACITY", "REO_CAPACITY", 31);
        $this->RegisterVariableInteger("hdd_remaining_capacity", "HDD_REMAINING_CAPACITY", "REO_CAPACITY", 32);
        $this->RegisterVariableBoolean("hdd_formatted", "HDD_FORMATTED", "", 33);
        $this->RegisterVariableBoolean("hdd_mounted", "HDD_MOUNTED", "", 34);

        // action register
        $this->EnableAction("bright");
        $this->EnableAction("contrast");
        $this->EnableAction("hue");
        $this->EnableAction("saturation");
        $this->EnableAction("sharpen");
        $this->EnableAction("time_format");
        $this->EnableAction("hour_format");

        // snapshot image
        if (@IPS_GetMediaIDByName("Snapshot", $this->InstanceID) == false) {
            $createdMediaID = IPS_CreateMedia(MEDIATYPE_IMAGE);
            IPS_SetName($createdMediaID, "Snapshot");
            IPS_SetPosition($createdMediaID, 2);
            IPS_Sleep(2000);
            IPS_SetMediaCached($createdMediaID, false);
            IPS_SetMediaFile($createdMediaID, "Snapshot" . $this->InstanceID . ".jpg", false);
            IPS_SetParent($createdMediaID, $this->InstanceID);
        }

        // last motion snapshot
        if (@IPS_GetMediaIDByName("LastMotionSnapshot", $this->InstanceID) == false) {
            $createdMediaID = IPS_CreateMedia(MEDIATYPE_IMAGE);
            IPS_SetName($createdMediaID, "LastMotionSnapshot");
            IPS_SetPosition($createdMediaID, 3);
            IPS_Sleep(2000);
            IPS_SetMediaCached($createdMediaID, false);
            IPS_SetMediaFile($createdMediaID, "LastMotionSnapshot" . $this->InstanceID . ".jpg", false);
            IPS_SetParent($createdMediaID, $this->InstanceID);
        }

        // live stream rtmp
        if (@IPS_GetMediaIDByName("LiveStream", $this->InstanceID) == false) {
            $createdMediaID = IPS_CreateMedia(MEDIATYPE_STREAM);
            IPS_SetName($createdMediaID, "LiveStream");
            IPS_SetPosition($createdMediaID, 3);
            IPS_Sleep(2000);
            IPS_SetMediaCached($createdMediaID, false);
            IPS_SetParent($createdMediaID, $this->InstanceID);
        }
    }

    /**
     * register Profiles for IP-Symcon layout
     * @return void
     */
    protected function registerProfiles() {
        // Generate Variable Profiles
        if (!IPS_VariableProfileExists('REO_CAPACITY')) {
            IPS_CreateVariableProfile('REO_CAPACITY', 1,);
            IPS_SetVariableProfileText("REO_CAPACITY", "", "Mb");
        }
        // Generate Variable Profiles
        if (!IPS_VariableProfileExists('REO_TIME_FORMAT')) {
            IPS_CreateVariableProfile('REO_TIME_FORMAT', 3);
            IPS_SetVariableProfileAssociation("REO_TIME_FORMAT", "MM/DD/YYYY", "MM/DD/YYYY", "", 0x828282);
            IPS_SetVariableProfileAssociation("REO_TIME_FORMAT", "YYYY/MM/DD", "YYYY/MM/DD", "", 0x828282);
            IPS_SetVariableProfileAssociation("REO_TIME_FORMAT", "DD/MM/YYYY", "DD/MM/YYYY", "", 0x828282);
        }
        // Generate Variable Profiles
        if (!IPS_VariableProfileExists('REO_HOUR_FORMAT')) {
            IPS_CreateVariableProfile('REO_HOUR_FORMAT', 1);
            IPS_SetVariableProfileAssociation("REO_HOUR_FORMAT", 0, "24 hours", "", 0x828282);
            IPS_SetVariableProfileAssociation("REO_HOUR_FORMAT", 1, "12 hours", "", 0x828282);
        }
    }

    /**
     * This Function will return the IP Symcon needed form.json
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
                "type" => "RowLayout",
                "items" => [
                    [
                        "type" => "NumberSpinner",
                        "name" => "scheduledCommon",
                        "caption" => "Variables refresh rate",
                        "suffix" => "min.",
                        "minimum" => 5,
                        "maximum" => 15
                    ],
                    [
                        "type" => "NumberSpinner",
                        "name" => "scheduledImportant",
                        "caption" => "Motion refresh rate",
                        "suffix" => "sec.",
                        "minimum" => 2
                    ],
                    [
                        "type" => "NumberSpinner",
                        "name" => "imageGrabberRate",
                        "caption" => "Snapshot update rate",
                        "suffix" => "sec.",
                        "minimum" => 2
                    ]
                ]
            ],
            [
                "type" => "RowLayout",
                "items" => [
                    [
                        "type" => "CheckBox",
                        "name" => "imageGrabber",
                        "caption" => "Snapshot update"
                    ],
                    [
                        "type" => "CheckBox",
                        "name" => "saveImageOnMotion",
                        "caption" => "Save image on motion"
                    ],
                    [
                        "type" => "CheckBox",
                        "name" => "log",
                        "caption" => "Log"
                    ]
                ]
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