<?
	class ProxonPanel extends IPSModuleStrict {
		public function Create(): void {
			//Never delete this line!
			parent::Create();
			
			$this->RegisterPropertyInteger("ControlPanel", 1);
			$this->RegisterPropertyInteger("Interval", 30);

			$this->RegisterTimer("Poller", 0, "PROXON_FWT1_RequestStatus(\$_IPS['TARGET']);");
 
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
				"TEMPLATE" => VARIABLE_TEMPLATE_SLIDER_ROOM_TEMPERATURE
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
			// We use "modulo 20" to target the HNBP, which has ControlPanel ID 20,
			// but in the ModBus Address space comes always first, therefore Address + 0
			
			// CurrentTemperature -> FC3, 130 + X, INT16 (0.1 °C Resolution)
			$Address = 150 + ($this->ReadPropertyInteger("ControlPanel") % 20);
			$Data = $this->SendDataToParent(json_encode(Array("DataID" => "{E310B701-4AE7-458E-B618-EC13A1A6F6A8}", "Function" => 3, "Address" => $Address , "Quantity" => 1, "Data" => "")));
			if($Data == false)
				return;
			$Data = (unpack("n*", substr($Data,2)));
			// CurrentTemperature is a signed value, so we need to convert it (there is no value for unpacking a signed short)
			if($Data[1] >= pow(2, 15)) $Data[1] -= pow(2, 16);
			$this->SetValue("CurrentTemperature", $Data[1] / 10.0);

			// BaseTemperature -> FC3, 220 + X, INT16 (1.0 °C Resolution)
			// Only for Panels > 1
			if ($this->ReadPropertyInteger("ControlPanel") > 1) {
				$Address = 220 + (($this->ReadPropertyInteger("ControlPanel")-1) % 20);
				$Data = $this->SendDataToParent(json_encode(Array("DataID" => "{E310B701-4AE7-458E-B618-EC13A1A6F6A8}", "Function" => 3, "Address" => $Address , "Quantity" => 1, "Data" => "")));
				if($Data == false)
					return;
				$BaseTemperature = (unpack("n*", substr($Data,2)));
				// BaseTemperature is a signed value, so we need to convert it (there is no value for unpacking a signed short)
				if($BaseTemperature[1] >= pow(2, 15)) $BaseTemperature[1] -= pow(2, 16);

				// We want to store the BaseTemperature in a buffer, to use it for SetTemperature
				$this->SetBuffer("BaseTemperature", $BaseTemperature[1]);
			}

			// TargetTemperature -> FC3, 180 + X, INT16 (1.0 °C Resolution)
			$Address = 180 + ($this->ReadPropertyInteger("ControlPanel") % 20);
			$Data = $this->SendDataToParent(json_encode(Array("DataID" => "{E310B701-4AE7-458E-B618-EC13A1A6F6A8}", "Function" => 3, "Address" => $Address , "Quantity" => 1, "Data" => "")));
			if($Data == false)
				return;
			$TargetTemperature = (unpack("n*", substr($Data,2)));
			// OffsetTemperature is a signed value, so we need to convert it (there is no value for unpacking a signed short)
			if($TargetTemperature[1] >= pow(2, 15)) $TargetTemperature[1] -= pow(2, 16);

			$this->SetValue("TargetTemperature", $TargetTemperature[1]);
			
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
		}

		public function SetTemperature(int $Value): void {
			/*if ($this->ReadPropertyInteger("ControlPanel") > 1) {
				$BaseTemperature = $this->GetBuffer("BaseTemperature");
				if ($BaseTemperature === "") {
					die($this->Translate("A current value must be available before a new target temperature can be set."));
				}
				
				$OffsetTemperature = $Value - intval($BaseTemperature);
			} else {*/
				// Set always absolute value
				$OffsetTemperature = $Value;
			//}
		
			// OffsetTemperature -> FC6, 200 + X, INT16 (1.0 °C Resolution)
			$Address = 200 + ($this->ReadPropertyInteger("ControlPanel") % 20);
			$Data = pack("n*", $OffsetTemperature);
			$this->SendDataToParent(json_encode(Array("DataID" => "{E310B701-4AE7-458E-B618-EC13A1A6F6A8}", "Function" => 6, "Address" => $Address , "Quantity" => 1, "Data" => bin2hex($Data))));

			$this->SetValue("TargetTemperature", $Value);
		}

		public function SetPTC(bool $Release): void {
			// PTCRelease -> FC6, 302 INT16 (0 = Gesperrt, 1 = Freigegeben)
			$Address = 302 + ($this->ReadPropertyInteger("ControlPanel") % 20);

			//Create BitMask for Panel
			$bit = 1 << ($this->ReadPropertyInteger("ControlPanel") - 1);

			if ($Release) {
				$in_value |= $bit;      // EIN
			} else {
				$in_value &= ~$bit;     // AUS
			}

			$Data = pack("n*", $in_value);
			$this->SendDataToParent(json_encode(Array("DataID" => "{E310B701-4AE7-458E-B618-EC13A1A6F6A8}", "Function" => 6, "Address" => $Address , "Quantity" => 1, "Data" => bin2hex($Data))));

			$this->SetValue("PTCRelease", $Release);
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