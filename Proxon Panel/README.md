# Pulszähler
Das Modul ermöglicht es, eine Einzelraumsteuerung einer Zimmermann PROXON FrischluftWärmetechnik Anlage 1.0 via Modbus TCP zu implementieren

### Inhaltsverzeichnis

1. [Funktionsumfang](#1-funktionsumfang)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Software-Installation](#3-software-installation)
4. [Einrichten der Instanzen in IP-Symcon](#4-einrichten-der-instanzen-in-ip-symcon)
5. [Statusvariablen und Profile](#5-statusvariablen-und-profile)
6. [Konfiguration](#6-konfiguration)
7. [Visualisierung](#7-visualisierung)
8. [PHP-Befehlsreferenz](#8-php-befehlsreferenz)


### 1. Funktionsumfang

* Zeigt Ist-Temperatur & PTC Status
* Kann Soll-Temperatur sowie PTC Freigabe schreiben

### 2. Voraussetzungen

- IP-Symcon ab Version 8.0

### 3. Software-Installation

* Über den Module Store das 'Proxon FWT1'-Modul installieren.
* Alternativ über das Module Control folgende URL hinzufügen: https://github.com/migodev/proxon_fwt1

### 4. Einrichten der Instanzen in IP-Symcon

 Unter 'Instanz hinzufügen' kann das 'Proxon FWT1'-Modul mithilfe des Schnellfilters gefunden werden.  
	- Weitere Informationen zum Hinzufügen von Instanzen in der [Dokumentation der Instanzen](https://www.symcon.de/service/dokumentation/konzepte/instanzen/#Instanz_hinzufügen)

### 5. Statusvariablen und Profile

Es werden keine Profile angelegt.
Es werden 4 Statusvariablen angelegt:

Name                  | Typ					| Funktion
--------------------- | ------------------- | -------------------
Ist Temperatur 		  | Float				| AKtuelle Ist-Temperatur am Bedienteil
Soll Temperatur  	  | Integer				| AKtuelle Soll Temperatur
PTC Freigabe		  | Boolean				| Aktiviert/Deaktiviert die Freigabe des PTC Elements
PCT Zustand           | Boolean				| Zeigt den Zustand des PTC Elements an

### 6. Konfiguration

| Eigenschaft                                           |   Typ   | Standardwert | Funktion                                                  |
|:------------------------------------------------------|:-------:|:-------------|:----------------------------------------------------------|
| Bedienpanel                                           | integer | 0            | Bedienpanel ID, in der Regel 1-10                         |
| Intervall                                           	| integer | 0            | Intervall in Sekunden in denen die Werte abgefragt werden |


### 6. Visualisierung

Das Modul bietet in der Visualisierung die Möglichkeit den die SOll-Temperatur zu verändern und den PTC Freigabe Modus zu aktivieren. Ist Temperatur sowie PTC STatus werden dargestellt.

### 7. PHP-Befehlsreferenz

Über die Methode PXNFWT1_RequestStatus kann von außerhalb eine Aktualisierung der Daten angestoßen werden.