<?
	class ProxonPanel extends IPSModuleStrict {
		public function Create(): void {
			//Never delete this line!
			parent::Create();
			
			$this->RegisterPropertyInteger("ControlPanel", 1);
			$this->RegisterPropertyInteger("Interval", 30);

			$this->RegisterAttributeFloat('BaseTemperature', 0);

			$this->RegisterTimer("Poller", 0, "PROXONFWT1_RequestStatus(\$_IPS['TARGET']);");
 
		}

		public function ApplyChanges(): void {
			//Never delete this line!
			parent::ApplyChanges();
			
			$this->RegisterVariableFloat("CurrentTemperature", $this->Translate("Current Temperature"), [
				"PRESENTATION" => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
				"TEMPLATE" => VARIABLE_TEMPLATE_VALUE_PRESENTATION_ROOM_TEMPERATURE
			], 1);
			$this->RegisterVariableInteger("TargetTemperature", $this->Translate("Target Temperature"), [
				"PRESENTATION" => VARIABLE_PRESENTATION_SLIDER,
				"TEMPLATE" => VARIABLE_TEMPLATE_SLIDER_ROOM_TEMPERATURE,
				"MIN" => 18,
				"MAX" => 24,
				"STEP_SIZE" => 1,
				"USAGE_TYPE" => 0,
				"GRADIENT_TYPE" => 1, 
				"SUFFIX" => ' °C', 
				'ICON' => 'temperature-half'
			], 2);

			$this->EnableAction("TargetTemperature");
			$this->RegisterVariableBoolean("PTCRelease", $this->Translate("PTC Release"), [
				"PRESENTATION" => VARIABLE_PRESENTATION_SWITCH
			], 3);
			$this->EnableAction("PTCRelease");

			$this->RegisterVariableBoolean("PTCStatus", $this->Translate("PTC Status"), [
				"PRESENTATION" =>  	VARIABLE_PRESENTATION_VALUE_PRESENTATION
			], 3);

			$this->SetTimerInterval("Poller", $this->ReadPropertyInteger("Interval") * 1000);
		}

		public function RequestStatus(): void {		
			// CurrentTemperature -> FC3, 150 + X, INT16 (0.1 °C Resolution)
			$Address = 150 + ($this->ReadPropertyInteger("ControlPanel") - 1);
			$Data = $this->SendDataToParent(json_encode(Array("DataID" => "{E310B701-4AE7-458E-B618-EC13A1A6F6A8}", "Function" => 3, "Address" => $Address , "Quantity" => 1, "Data" => "")));
			if($Data == false)
				return;
			$Data = (unpack("n*", substr($Data,2)));
			// CurrentTemperature is a signed value, so we need to convert it (there is no value for unpacking a signed short)
			if($Data[1] >= pow(2, 15)) $Data[1] -= pow(2, 16);
			$this->SetValue("CurrentTemperature", $Data[1] / 10.0);
			$this->SendDebug('current-temp', "get current temp for Panel ".$this->ReadPropertyInteger("ControlPanel")." with value: ".($Data[1] / 10.0)." Address: ".$Address." - Function: 3", 0);

			// BaseTemperature -> FC3, 220 + X, INT16 (1.0 °C Resolution)
			// Only for Panels > 1
			if ($this->ReadPropertyInteger("ControlPanel") > 1) {
				$Address = 220 + ($this->ReadPropertyInteger("ControlPanel")-1);
				$Data = $this->SendDataToParent(json_encode(Array("DataID" => "{E310B701-4AE7-458E-B618-EC13A1A6F6A8}", "Function" => 3, "Address" => $Address , "Quantity" => 1, "Data" => "")));
				if($Data == false)
					return;
				$BaseTemperature = (unpack("n*", substr($Data,2)));
				// BaseTemperature is a signed value, so we need to convert it (there is no value for unpacking a signed short)
				if($BaseTemperature[1] >= pow(2, 15)) $BaseTemperature[1] -= pow(2, 16);

				$baseTemp = $BaseTemperature[1] / 10.0;

				// Read last value of BaseTemperature
				$oldBaseTemp = $this->ReadAttributeFloat('BaseTemperature');

				// Edit Presentation only on Change
				if ($baseTemp != $oldBaseTemp) {
					// We want to store the BaseTemperature in a attribute, to use it for SetTemperature / comparison
					$this->WriteAttributeFloat('BaseTemperature', $baseTemp);

					$this->SendDebug('base-temp', "read base temp for Panel ".$this->ReadPropertyInteger("ControlPanel")." with value: ".$baseTemp." Address: ".$Address." - Function: 3", 0);

					$minTemp = $baseTemp-3;
					$maxTemp = $baseTemp+3;
					$id = $this->GetIDForIdent("TargetTemperature");
					IPS_SetVariableCustomPresentation($id, ['PRESENTATION' => VARIABLE_PRESENTATION_SLIDER, "TEMPLATE" => VARIABLE_TEMPLATE_SLIDER_ROOM_TEMPERATURE, 'MIN' => $minTemp, 'MAX' => $maxTemp, 'STEP_SIZE' => 1, "USAGE_TYPE" => 0, "GRADIENT_TYPE" => 1, "SUFFIX" => ' °C', 'ICON' => 'temperature-half']);
					$this->SendDebug('presentation', "set new presentation values for Panel ".$this->ReadPropertyInteger("ControlPanel")." with value: Min: ".$minTemp." / Max: ".$maxTemp, 0);
				}
			}

			// TargetTemperature -> FC3, 180 + X, INT16 (1.0 °C Resolution)
			$Address = 180 + ($this->ReadPropertyInteger("ControlPanel") - 1);
			$Data = $this->SendDataToParent(json_encode(Array("DataID" => "{E310B701-4AE7-458E-B618-EC13A1A6F6A8}", "Function" => 3, "Address" => $Address , "Quantity" => 1, "Data" => "")));
			if($Data == false)
				return;
			$TargetTemperature = (unpack("n*", substr($Data,2)));
			// OffsetTemperature is a signed value, so we need to convert it (there is no value for unpacking a signed short)
			if($TargetTemperature[1] >= pow(2, 15)) $TargetTemperature[1] -= pow(2, 16);

			$this->SetValue("TargetTemperature", $TargetTemperature[1] / 10.0);
			$this->SendDebug('target-temp', "get target temp for Panel ".$this->ReadPropertyInteger("ControlPanel")." with value: ".($TargetTemperature[1] / 10.0)." Address: ".$Address." - Function: 3", 0);
			

			// PTCRelease -> FC3, 302 Bitmask, INT16 (0 = Gesperrt, 1 = Freigegeben)
			$Address = 302;
			$Data = $this->SendDataToParent(json_encode(Array("DataID" => "{E310B701-4AE7-458E-B618-EC13A1A6F6A8}", "Function" => 3, "Address" => $Address , "Quantity" => 1, "Data" => "")));
			if($Data == false)
				return;
			$Data = (unpack("n*", substr($Data,2)));
			$decBitMask = $Data[1];

			//Create BitMask for Panel
			$bitmask = 1 << ($this->ReadPropertyInteger("ControlPanel") - 1);
			// Prüfe ob das Bit gesetzt ist
			$bitCheck = ($decBitMask & $bitmask) !== 0;

			$this->SetValue("PTCRelease", $bitCheck);	
			$this->SendDebug('ptc-release', "Get Status for Panel ".$this->ReadPropertyInteger("ControlPanel")." with value: ".$decBitMask." - Bit: ".$bitmask."", 0);


			// PTCStatus -> FC3, 300 Bitmask, INT16 (0 = Gesperrt, 1 = Freigegeben)
			$Address = 300;
			$Data = $this->SendDataToParent(json_encode(Array("DataID" => "{E310B701-4AE7-458E-B618-EC13A1A6F6A8}", "Function" => 3, "Address" => $Address , "Quantity" => 1, "Data" => "")));
			if($Data == false)
				return;
			$Data = (unpack("n*", substr($Data,2)));
			$decBitMask = $Data[1];

			//Create BitMask for Panel
			$bitmask = 1 << ($this->ReadPropertyInteger("ControlPanel") - 1);
			// Prüfe ob das Bit gesetzt ist
			$bitCheck = ($decBitMask & $bitmask) !== 0;

			$this->SetValue("PTCStatus", $bitCheck);	
			$this->SendDebug('ptc-status', "Get Status for Panel ".$this->ReadPropertyInteger("ControlPanel")." with value: ".$decBitMask." - Bit: ".$bitmask."", 0);
		}

		public function SetTemperature(int $Value): void {
			/*if ($this->ReadPropertyInteger("ControlPanel") > 1) {
				$BaseTemperature = $this->ReadAttributeFloat('BaseTemperature'); //$this->GetBuffer("BaseTemperature");
				if ($BaseTemperature === "") {
					die($this->Translate("A current value must be available before a new target temperature can be set."));
				}
				
				$OffsetTemperature = $Value - intval($BaseTemperature);
			} else {*/
				// Set always absolute value
				$OffsetTemperature = $Value;
			//}
		
			// OffsetTemperature -> FC6, 200 + X, INT16 (1.0 °C Resolution)
			$Address = 200 + ($this->ReadPropertyInteger("ControlPanel") - 1);
			$Data = pack("n*", $OffsetTemperature);
			$this->SendDataToParent(json_encode(Array("DataID" => "{E310B701-4AE7-458E-B618-EC13A1A6F6A8}", "Function" => 6, "Address" => $Address , "Quantity" => 1, "Data" => bin2hex($Data))));

			$this->SetValue("TargetTemperature", $Value);
			$this->SendDebug('target-temp', "set target temp for Panel ".$this->ReadPropertyInteger("ControlPanel")." with value: ".$Value, 0);
		}

		public function SetPTC(bool $Release): void {
			// PTCRelease -> FC3, 302 Bitmask, INT16 (0 = Gesperrt, 1 = Freigegeben)
			$Address = 302;
			$Data = $this->SendDataToParent(json_encode(Array("DataID" => "{E310B701-4AE7-458E-B618-EC13A1A6F6A8}", "Function" => 3, "Address" => $Address , "Quantity" => 1, "Data" => "")));
			if($Data == false)
				return;
			$Data = (unpack("n*", substr($Data,2)));
			$decBitMask = $Data[1];
		
			// PTCRelease -> FC3, 301 Bitmask, INT16 (0 = Gesperrt, 1 = Freigegeben)
			$Address = 301;

			//Create BitMask for Panel
			$bit = 1 << ($this->ReadPropertyInteger("ControlPanel") - 1);

			if ($Release) {
				$decBitMask |= $bit;      // EIN
			} else {
				$decBitMask &= ~$bit;     // AUS
			}

			$Data = pack("n*", $decBitMask);
			$this->SendDataToParent(json_encode(Array("DataID" => "{E310B701-4AE7-458E-B618-EC13A1A6F6A8}", "Function" => 6, "Address" => $Address , "Quantity" => 1, "Data" => bin2hex($Data))));

			$this->SetValue("PTCRelease", $Release);
			$this->SendDebug('ptc-release', "Set Status for Panel ".$this->ReadPropertyInteger("ControlPanel")." with value: ".$decBitMask." - TargetState: ".json_encode($Release), 0);

		}

		public function RequestAction(string $Ident, mixed $Value): void {
			switch($Ident) {
				case "TargetTemperature":
					$this->SetTemperature($Value);
					break;
				case "PTCRelease":
					$this->SetPTC($Value);
					break;
			}
		}
	}
?>