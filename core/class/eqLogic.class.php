<?php

/* This file is part of Jeedom.
*
* Jeedom is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* Jeedom is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
*/

/* * ***************************Includes********************************* */
require_once __DIR__ . '/../../core/php/core.inc.php';

class eqLogic {
	/*     * *************************Attributs****************************** */
	const UIDDELIMITER = '__';
	protected $id;
	protected $name;
	protected $logicalId = '';
	protected $generic_type;
	protected $object_id = null;
	protected $eqType_name;
	protected $isVisible = 0;
	protected $isEnable = 0;
	protected $configuration;
	protected $timeout = 0;
	protected $category;
	protected $display;
	protected $order = 9999;
	protected $comment;
	protected $tags;
	protected $_debug = false;
	protected $_object = null;
	protected $_needRefreshWidget = false;
	protected $_timeoutUpdated = false;
	protected $_batteryUpdated = false;
	protected $_changed = false;

	protected $_cmds = array();

	protected static $_templateArray = array();

	/*     * ***********************Méthodes statiques*************************** */

	public static function getAllTags() {
		$values = array();
		$sql = 'SELECT tags
		FROM eqLogic
		WHERE tags IS NOT NULL
		AND tags!=""';
		$results = DB::Prepare($sql, $values, DB::FETCH_TYPE_ALL);
		$return = array();
		foreach ($results as $result) {
			$tags = explode(',', $result['tags']);
			foreach ($tags as $tag) {
				$return[$tag] = $tag;
			}
		}
		return $return;
	}

	/**
	 * @param int|string $_id
	 * @return void|eqLogic void if $_id is not valid else the eqLogic
	 */
	public static function byId($_id) {
		if ($_id == '') {
			return;
		}
		$values = array(
			'id' => $_id,
		);
		$sql = 'SELECT ' . DB::buildField(__CLASS__) . '
		FROM eqLogic
		WHERE id=:id';
		return self::cast(DB::Prepare($sql, $values, DB::FETCH_TYPE_ROW, PDO::FETCH_CLASS, __CLASS__));
	}

	private static function cast($_inputs) {
		if (is_object($_inputs) && class_exists($_inputs->getEqType_name())) {
			$return = cast($_inputs, $_inputs->getEqType_name());
			if (method_exists($return, 'decrypt')) {
				$return->decrypt();
			}
			return $return;
		}
		if (is_array($_inputs)) {
			$return = array();
			foreach ($_inputs as $input) {
				$return[] = self::cast($input);
			}
			return $return;
		}
		return $_inputs;
	}

	public static function all($_onlyEnable = false) {
		$sql = 'SELECT ' . DB::buildField(__CLASS__, 'el') . '
		FROM eqLogic el
		LEFT JOIN object ob ON el.object_id=ob.id';
		if ($_onlyEnable) {
			$sql .= ' AND isEnable=1';
		}
		$sql .= ' ORDER BY ob.name,el.name';
		return self::cast(DB::Prepare($sql, array(), DB::FETCH_TYPE_ALL, PDO::FETCH_CLASS, __CLASS__));
	}

	public static function byObjectId($_object_id, $_onlyEnable = true, $_onlyVisible = false, $_eqType_name = null, $_logicalId = null, $_orderByName = false, $_onlyHasCmds = false) {
		$values = array();
		$sql = 'SELECT ' . DB::buildField(__CLASS__) . '
		FROM eqLogic';
		if ($_object_id === null) {
			$sql .= ' WHERE (object_id IS NULL OR object_id = -1)';
		} else {
			$values['object_id'] = $_object_id;
			$sql .= ' WHERE object_id=:object_id';
		}
		if ($_onlyEnable) {
			$sql .= ' AND isEnable = 1';
		}
		if ($_onlyVisible) {
			$sql .= ' AND isVisible = 1';
		}
		if ($_eqType_name !== null) {
			$values['eqType_name'] = $_eqType_name;
			$sql .= ' AND eqType_name=:eqType_name';
		}
		if ($_logicalId !== null) {
			$values['logicalId'] = $_logicalId;
			$sql .= ' AND logicalId=:logicalId';
		}
		if ($_onlyHasCmds !== false) {
			$sql .= ' AND id IN (SELECT eqLogic_id FROM cmd WHERE 1=1';
			if (is_array($_onlyHasCmds)) {
				if (isset($_onlyHasCmds['type'])) {
					$values['cmd_type'] = $_onlyHasCmds['type'];
					$sql .= ' AND type=:cmd_type';
				}
				if (isset($_onlyHasCmds['subType'])) {
					$values['cmd_subType'] = $_onlyHasCmds['subType'];
					$sql .= ' AND subType=:cmd_subType';
				}
			}
			$sql .= ' )';
		}
		if ($_orderByName) {
			$sql .= ' ORDER BY `name`';
		} else {
			$sql .= ' ORDER BY `order`,category';
		}
		if ($_eqType_name != null && class_exists($_eqType_name)) {
			return DB::Prepare($sql, $values, DB::FETCH_TYPE_ALL, PDO::FETCH_CLASS, $_eqType_name);
		}
		return self::cast(DB::Prepare($sql, $values, DB::FETCH_TYPE_ALL, PDO::FETCH_CLASS, __CLASS__));
	}

	/**
	 * @param string $_logicalId
	 * @param string $_eqType_name the plugin class name
	 * @param bool $_multiple default value=false
	 * @return eqLogic|eqLogic[] eqLogic if $_multiple is false else eqLogic[]
	 */
	public static function byLogicalId($_logicalId, $_eqType_name, $_multiple = false) {
		$values = array(
			'logicalId' => $_logicalId,
			'eqType_name' => $_eqType_name,
		);
		$sql = 'SELECT ' . DB::buildField(__CLASS__) . '
		FROM eqLogic
		WHERE logicalId=:logicalId
		AND eqType_name=:eqType_name';
		if ($_multiple) {
			if (class_exists($_eqType_name)) {
				return DB::Prepare($sql, $values, DB::FETCH_TYPE_ALL, PDO::FETCH_CLASS, $_eqType_name);
			}
			return self::cast(DB::Prepare($sql, $values, DB::FETCH_TYPE_ALL, PDO::FETCH_CLASS, __CLASS__));
		}
		if (class_exists($_eqType_name)) {
			return DB::Prepare($sql, $values, DB::FETCH_TYPE_ROW, PDO::FETCH_CLASS, $_eqType_name);
		}
		return self::cast(DB::Prepare($sql, $values, DB::FETCH_TYPE_ROW, PDO::FETCH_CLASS, __CLASS__));
	}

	/**
	 * @param string $_eqType_name the plugin class name
	 * @param bool $_onlyEnable
	 * @return eqLogic[]
	 */
	public static function byType($_eqType_name, $_onlyEnable = false) {
		$values = array(
			'eqType_name' => $_eqType_name,
		);
		$sql = 'SELECT ' . DB::buildField(__CLASS__, 'el') . '
		FROM eqLogic el
		LEFT JOIN object ob ON el.object_id=ob.id
		WHERE eqType_name=:eqType_name ';
		if ($_onlyEnable) {
			$sql .= ' AND isEnable=1';
		}
		$sql .= ' ORDER BY ob.name,el.name';
		if (class_exists($_eqType_name)) {
			return DB::Prepare($sql, $values, DB::FETCH_TYPE_ALL, PDO::FETCH_CLASS, $_eqType_name);
		}
		return self::cast(DB::Prepare($sql, $values, DB::FETCH_TYPE_ALL, PDO::FETCH_CLASS, __CLASS__));
	}

	public static function byCategorie($_category) {
		$values = array(
			'category' => '%"' . $_category . '":1%',
			'category2' => '%"' . $_category . '":"1"%',
		);

		$sql = 'SELECT ' . DB::buildField(__CLASS__) . '
		FROM eqLogic
		WHERE category LIKE :category
		OR category LIKE :category2
		ORDER BY name';
		return self::cast(DB::Prepare($sql, $values, DB::FETCH_TYPE_ALL, PDO::FETCH_CLASS, __CLASS__));
	}

	public static function byTypeAndSearchConfiguration($_eqType_name, $_configuration, $_onlyEnable = false, $_onlyVisible = false) {
		if (is_array($_configuration)) {
			$values = array(
				'eqType_name' => $_eqType_name,
				'configuration' => json_encode($_configuration),
			);
			$sql = 'SELECT ' . DB::buildField(__CLASS__) . '
			FROM eqLogic
			WHERE eqType_name=:eqType_name';
			if ($_onlyEnable) {
				$sql .= ' AND isEnable=1';
			}
			if ($_onlyVisible) {
				$sql .= ' AND isVisible=1';
			}
			$sql .= ' AND JSON_CONTAINS(configuration,:configuration)
			ORDER BY name';
			if ($_eqType_name != null && class_exists($_eqType_name)) {
				return DB::Prepare($sql, $values, DB::FETCH_TYPE_ALL, PDO::FETCH_CLASS, $_eqType_name);
			}
			return self::cast(DB::Prepare($sql, $values, DB::FETCH_TYPE_ALL, PDO::FETCH_CLASS, __CLASS__));
		}
		$values = array(
			'eqType_name' => $_eqType_name,
			'configuration' => '%' . $_configuration . '%',
		);
		$sql = 'SELECT ' . DB::buildField(__CLASS__) . '
		FROM eqLogic
		WHERE eqType_name=:eqType_name';
		if ($_onlyEnable) {
			$sql .= ' AND isEnable=1';
		}
		if ($_onlyVisible) {
			$sql .= ' AND isVisible=1';
		}
		$sql .= '
		AND configuration LIKE :configuration
		ORDER BY name';
		if ($_eqType_name != null && class_exists($_eqType_name)) {
			return DB::Prepare($sql, $values, DB::FETCH_TYPE_ALL, PDO::FETCH_CLASS, $_eqType_name);
		}
		return self::cast(DB::Prepare($sql, $values, DB::FETCH_TYPE_ALL, PDO::FETCH_CLASS, __CLASS__));
	}

	public static function byTypeAndSearhConfiguration($_eqType_name, $_configuration,  $_onlyEnable = false, $_onlyVisible = false) {
		trigger_error('eqLogic::byTypeAndSearhConfiguration() is deprecated since Core v4.4, eqLogic::byTypeAndSearchConfiguration() has been introduced since Core v4.1', E_USER_DEPRECATED);
		return self::byTypeAndSearchConfiguration($_eqType_name, $_configuration, $_onlyEnable, $_onlyVisible);
	}

	public static function searchByString($_search) {
		$values = array(
			'search' => '%' . $_search . '%'
		);
		$sql = 'SELECT ' . DB::buildField(__CLASS__) . '
		FROM eqLogic
		WHERE name LIKE :search or logicalId LIKE :search or eqType_name LIKE :search or comment LIKE :search or tags LIKE :search';
		return self::cast(DB::Prepare($sql, $values, DB::FETCH_TYPE_ALL, PDO::FETCH_CLASS, __CLASS__));
	}

	public static function searchConfiguration($_configuration, $_eqType_name = null) {
		if (!is_array($_configuration)) {
			$values = array(
				'configuration' => '%' . $_configuration . '%',
			);
			$sql = 'SELECT ' . DB::buildField(__CLASS__) . '
			FROM eqLogic
			WHERE configuration LIKE :configuration';
		} else {
			$values = array(
				'configuration' => '%' . $_configuration[0] . '%',
			);
			$sql = 'SELECT ' . DB::buildField(__CLASS__) . '
			FROM eqLogic
			WHERE configuration LIKE :configuration';
			for ($i = 1; $i < count($_configuration); $i++) {
				$values['configuration' . $i] = '%' . $_configuration[$i] . '%';
				$sql .= ' OR configuration LIKE :configuration' . $i;
			}
		}
		if ($_eqType_name !== null) {
			$values['eqType_name'] = $_eqType_name;
			$sql .= ' AND eqType_name=:eqType_name ';
		}
		$sql .= ' ORDER BY name';
		if ($_eqType_name != null && class_exists($_eqType_name)) {
			return DB::Prepare($sql, $values, DB::FETCH_TYPE_ALL, PDO::FETCH_CLASS, $_eqType_name);
		}
		return self::cast(DB::Prepare($sql, $values, DB::FETCH_TYPE_ALL, PDO::FETCH_CLASS, __CLASS__));
	}

	public static function listByTypeAndCmdType($_eqType_name, $_typeCmd, $subTypeCmd = '') {
		if ($subTypeCmd == '') {
			$values = array(
				'eqType_name' => $_eqType_name,
				'typeCmd' => $_typeCmd,
			);
			$sql = 'SELECT DISTINCT(el.id),el.name
			FROM eqLogic el
			INNER JOIN cmd c ON c.eqLogic_id=el.id
			WHERE eqType_name=:eqType_name
			AND c.type=:typeCmd
			ORDER BY name';
			return DB::Prepare($sql, $values, DB::FETCH_TYPE_ALL);
		} else {
			$values = array(
				'eqType_name' => $_eqType_name,
				'typeCmd' => $_typeCmd,
				'subTypeCmd' => $subTypeCmd,
			);
			$sql = 'SELECT DISTINCT(el.id),el.name
			FROM eqLogic el
			INNER JOIN cmd c ON c.eqLogic_id=el.id
			WHERE eqType_name=:eqType_name
			AND c.type=:typeCmd
			AND c.subType=:subTypeCmd
			ORDER BY name';
			return DB::Prepare($sql, $values, DB::FETCH_TYPE_ALL);
		}
	}

	public static function listByObjectAndCmdType($_object_id, $_typeCmd, $subTypeCmd = '') {
		$values = array();
		$sql = 'SELECT DISTINCT(el.id),el.name
		FROM eqLogic el
		INNER JOIN cmd c ON c.eqLogic_id=el.id
		WHERE ';
		if ($_object_id === null) {
			$sql .= ' object_id IS NULL ';
		} elseif ($_object_id != '') {
			$values['object_id'] = $_object_id;
			$sql .= ' object_id=:object_id ';
		}
		if ($subTypeCmd != '') {
			$values['subTypeCmd'] = $subTypeCmd;
			$sql .= ' AND c.subType=:subTypeCmd ';
		}
		if ($_typeCmd != '' && $_typeCmd != 'all') {
			$values['type'] = $_typeCmd;
			$sql .= ' AND c.type=:type ';
		}
		$sql .= ' ORDER BY name ';
		return DB::Prepare($sql, $values, DB::FETCH_TYPE_ALL);
	}

	public static function allType() {
		$sql = 'SELECT distinct(eqType_name) as type
		FROM eqLogic';
		return DB::Prepare($sql, array(), DB::FETCH_TYPE_ALL);
	}

	public static function checkAlive() {
		foreach (eqLogic::byTimeout(1, true) as $eqLogic) {
			$sendReport = false;
			$cmds = $eqLogic->getCmd();
			foreach ($cmds as $cmd) {
				$sendReport = true;
			}
			$logicalId = 'noMessage' . $eqLogic->getId();
			if ($sendReport) {
				$noReponseTimeLimit = $eqLogic->getTimeout();
				if (count(message::byPluginLogicalId('core', $logicalId)) == 0) {
					if ($eqLogic->getStatus('lastCommunication', date('Y-m-d H:i:s')) < date('Y-m-d H:i:s', strtotime('-' . $noReponseTimeLimit . ' minutes' . date('Y-m-d H:i:s')))) {
						$message = __('Attention', __FILE__) . ' ' . $eqLogic->getHumanName();
						$message .= ' ' . __('n\'a pas envoyé de message depuis plus de', __FILE__) . ' ' . $noReponseTimeLimit . ' ' . __('min', __FILE__);
						$action = '<a href="/' . $eqLogic->getLinkToConfiguration() . '">' . __('Equipement', __FILE__) . '</a>';
						$prevStatus = $eqLogic->getStatus('timeout', 0);
						$eqLogic->setStatus('timeout', 1);
						if (config::byKey('alert::addMessageOnTimeout') == 1 && $prevStatus == 0) {
							message::add('core', $message, $action, $logicalId);
						}
						$cmds = explode(('&&'), config::byKey('alert::timeoutCmd'));
						if (count($cmds) > 0 && trim(config::byKey('alert::timeoutCmd')) != '' && $prevStatus == 0) {
							foreach ($cmds as $id) {
								$cmd = cmd::byId(str_replace('#', '', $id));
								if (is_object($cmd)) {
									$cmd->execCmd(array(
										'title' => '[' . config::byKey('name', 'core', 'JEEDOM') . '] : ' . $message,
										'message' => config::byKey('name', 'core', 'JEEDOM') . ' : ' . $message,
									));
								}
							}
						}
					}
				} else {
					if ($eqLogic->getStatus('lastCommunication', date('Y-m-d H:i:s')) > date('Y-m-d H:i:s', strtotime('-' . $noReponseTimeLimit . ' minutes' . date('Y-m-d H:i:s')))) {
						foreach (message::byPluginLogicalId('core', $logicalId) as $message) {
							$message->remove();
						}
						$eqLogic->setStatus('timeout', 0);
					}
				}
			}
		}
	}

	public static function byTimeout($_timeout = 0, $_onlyEnable = false) {
		$values = array(
			'timeout' => $_timeout,
		);
		$sql = 'SELECT ' . DB::buildField(__CLASS__) . '
		FROM eqLogic
		WHERE timeout>=:timeout';
		if ($_onlyEnable) {
			$sql .= ' AND isEnable=1';
		}
		return self::cast(DB::Prepare($sql, $values, DB::FETCH_TYPE_ALL, PDO::FETCH_CLASS, __CLASS__));
	}

	public static function byObjectNameEqLogicName($_object_name, $_eqLogic_name) {
		if ($_object_name == __('Aucun', __FILE__)) {
			$values = array(
				'eqLogic_name' => $_eqLogic_name,
			);
			$sql = 'SELECT ' . DB::buildField(__CLASS__) . '
			FROM eqLogic
			WHERE name=:eqLogic_name
			AND object_id IS NULL';
		} else {
			$values = array(
				'eqLogic_name' => $_eqLogic_name,
				'object_name' => $_object_name,
			);
			$sql = 'SELECT ' . DB::buildField(__CLASS__, 'el') . '
			FROM eqLogic el
			INNER JOIN object ob ON el.object_id=ob.id
			WHERE el.name=:eqLogic_name
			AND ob.name=:object_name';
		}
		return self::cast(DB::Prepare($sql, $values, DB::FETCH_TYPE_ALL, PDO::FETCH_CLASS, __CLASS__));
	}

	public static function toHumanReadable($_input) {
		if (is_object($_input)) {
			$reflections = array();
			$uuid = spl_object_hash($_input);
			if (!isset($reflections[$uuid])) {
				$reflections[$uuid] = new ReflectionClass($_input);
			}
			$reflection = $reflections[$uuid];
			$properties = $reflection->getProperties();
			foreach ($properties as $property) {
				$property->setAccessible(true);
				$value = $property->getValue($_input);
				$property->setValue($_input, self::toHumanReadable($value));
				$property->setAccessible(false);
			}
			return $_input;
		}
		if (is_array($_input)) {
			foreach ($_input as $key => $value) {
				$_input[$key] = self::toHumanReadable($value);
			}
			return $_input;
		}
		$text = $_input;
		preg_match_all("/#eqLogic([0-9]*)#/", $text, $matches);
		foreach ($matches[1] as $eqLogic_id) {
			if (is_numeric($eqLogic_id)) {
				$eqLogic = self::byId($eqLogic_id);
				if (is_object($eqLogic)) {
					$text = str_replace('#eqLogic' . $eqLogic_id . '#', '#' . $eqLogic->getHumanName() . '#', $text);
				}
			}
		}
		return $text;
	}

	public static function fromHumanReadable($_input) {
		if (empty($_input)) {
			return $_input;
		}
		$isJson = false;
		if (is_json($_input)) {
			$isJson = true;
			$_input = json_decode($_input, true);
		}
		if (is_object($_input)) {
			$reflections = array();
			$uuid = spl_object_hash($_input);
			if (!isset($reflections[$uuid])) {
				$reflections[$uuid] = new ReflectionClass($_input);
			}
			$reflection = $reflections[$uuid];
			$properties = $reflection->getProperties();
			foreach ($properties as $property) {
				$property->setAccessible(true);
				$value = $property->getValue($_input);
				$property->setValue($_input, self::fromHumanReadable($value));
				$property->setAccessible(false);
			}
			return $_input;
		}
		if (is_array($_input)) {
			foreach ($_input as $key => $value) {
				$_input[$key] = self::fromHumanReadable($value);
			}
			if ($isJson) {
				return json_encode($_input, JSON_UNESCAPED_UNICODE);
			}
			return $_input;
		}
		$text = $_input;
		preg_match_all("/#\[(.*?)\]\[(.*?)\]#/", $text, $matches);
		if (count($matches) == 3) {
			$countMatches = count($matches[0]);
			for ($i = 0; $i < $countMatches; $i++) {
				if (isset($matches[1][$i]) && isset($matches[2][$i])) {
					$eqLogic = self::byObjectNameEqLogicName($matches[1][$i], $matches[2][$i]);
					if (isset($eqLogic[0]) && is_object($eqLogic[0])) {
						$text = str_replace($matches[0][$i], '#eqLogic' . $eqLogic[0]->getId() . '#', $text);
					}
				}
			}
		}
		return $text;
	}

	/**
	 * byString
	 *
	 * @param  string $_string
	 * @return \eqLogic
	 */
	public static function byString($_string) {
		$eqLogic = self::byId(str_replace(array('#', 'eqLogic'), '', self::fromHumanReadable($_string)));
		if (!is_object($eqLogic)) {
			throw new Exception(__('L\'équipement n\'a pas pu être trouvé :', __FILE__) . ' ' . $_string . ' => ' . self::fromHumanReadable($_string));
		}
		return $eqLogic;
	}

	public static function generateHtmlTable($_nbLine, $_nbColumn, $_options = array()) {
		$return = array('html' => '', 'replace' => array());
		if (!isset($_options['styletd'])) {
			$_options['styletd'] = '';
		}
		if (!isset($_options['center'])) {
			$_options['center'] = 0;
		}
		if (!isset($_options['styletable'])) {
			$_options['styletable'] = '';
		}

		$return['html'] .= '<table style="' . $_options['styletable'] . '" class="tableCmd" data-line="' . $_nbLine . '" data-column="' . $_nbColumn . '">';
		$return['html'] .= '<tbody>';
		for ($i = 1; $i <= $_nbLine; $i++) {
			$return['html'] .= '<tr>';
			for ($j = 1; $j <= $_nbColumn; $j++) {
				$styletd = (isset($_options['style::td::' . $i . '::' . $j]) && $_options['style::td::' . $i . '::' . $j] != '') ? $_options['style::td::' . $i . '::' . $j] : '';
				$attrs = '';
				$style = '';
				if (trim($styletd) != '') {
					foreach (explode(';', $styletd) as $value) {
						if ($value == '') continue;
						if (strpos($value, '=') !== false) {
							$attrs .= $value;
						} else {
							$style .= $value . ';';
						}
					}
				}
				$style = $_options['styletd'] . $style;
				$classTd = ($style != '') ? 'tableCmdcss' : '';
				$return['html'] .= '<td class="' . $classTd . (($_options['center'] == 1) ? ' tableCenter' : '') . '" style="' . $style . '" ' . $attrs . ' data-line="' . $i . '" data-column="' . $j . '">';
				if (isset($_options['text::td::' . $i . '::' . $j])) {
					$return['html'] .= $_options['text::td::' . $i . '::' . $j];
				}
				$return['html'] .= '#cmd::' . $i . '::' . $j . '#';
				$return['html'] .= '</td>';
				$return['tag']['#cmd::' . $i . '::' . $j . '#'] = '';
			}
			$return['html'] .= '</tr>';
		}
		$return['html'] .= '</tbody>';
		$return['html'] .= '</table>';

		return $return;
	}

	/*     * *********************Méthodes d'instance************************* */

	public function batteryWidget($_version = 'dashboard') {
		$html = '';
		$level = 'good';
		$niveau = '3';
		$battery = $this->getConfiguration('battery_type', 'none');
		$batteryTime = $this->getConfiguration('batterytime', 'NA');
		$batterySince = 'NA';
		if ($batteryTime != 'NA') {
			$batterySince = round((strtotime(date("Y-m-d")) - strtotime(date("Y-m-d", strtotime($batteryTime)))) / 86400, 1);
		}
		if (strpos($battery, ' ') !== false) {
			$battery = mb_substr(strrchr($battery, " "), 1);
		}
		$plugins = $this->getEqType_name();
		$object_name = 'Aucun';
		if (is_object($this->getObject())) {
			$object_name = $this->getObject()->getName();
		}
		if ($this->getStatus('battery') <= $this->getConfiguration('battery_danger_threshold', config::byKey('battery::danger'))) {
			$level = 'critical';
			$niveau = '0';
		} else if ($this->getStatus('battery') <= $this->getConfiguration('battery_warning_threshold', config::byKey('battery::warning'))) {
			$level = 'warning';
			$niveau = '1';
		} else if ($this->getStatus('battery') <= 75) {
			$niveau = '2';
		}
		$classAttr = $level . ' ' . $battery . ' ' . $plugins . ' ' . $object_name;
		$idAttr = $level . '__' . $battery . '__' . $plugins . '__' . $object_name;
		$html .= '<div class="eqLogic eqLogic-widget text-center ' . $classAttr . '" id="' . $idAttr . '" data-eqlogic_id="' . $this->getId() . '">';

		$eqName = $this->getName();
		if ($_version == 'mobile') {
			$html .= '<div class="widget-name"><span class="name">' . $eqName . '</span><span class="object">' . $object_name . '</span></div>';
		} else {
			$html .= '<div class="#battery widget-name"><a href="' . $this->getLinkToConfiguration() . '">' . $eqName . '</a><br><span>' . $object_name . '</span></div>';
		}
		$html .= '<div class="jeedom-batterie">';
		$html .= '<i class="icon jeedom-batterie' . $niveau . '"></i>';
		$html .= '<span>' . $this->getStatus('battery', -2) . '%</span>';
		$html .= '</div>';
		$html .= '<div>' . __('Le', __FILE__) . ' ' . date("Y-m-d H:i:s", strtotime($this->getStatus('batteryDatetime', __('inconnue', __FILE__)))) . '</div>';
		$html .= '<br>';
		$html .= '<span class="pull-left pluginName">' . ucfirst($this->getEqType_name()) . '</span>';
		if ($_version == 'mobile') {
			$html .= '<span class="pull-left batteryTime">';
		} else {
			$html .= '<span class="pull-left batteryTime cursor">';
		}
		if ($this->getConfiguration('battery_danger_threshold') != '' || $this->getConfiguration('battery_warning_threshold') != '') {
			$html .= '<i class="icon techno-fingerprint41 pull-right" title="' . __('Seuil manuel défini', __FILE__) . '"></i>';
		}
		if ($batteryTime != 'NA') {
			$text = __('Pile(s) changée(s) il y a', __FILE__) . ' ';
			$text .= ($batterySince > 1) ? $batterySince . __('jours', __FILE__) . ' (' . $batteryTime . ')' : $batterySince . __('jour', __FILE__) . ' (' . $batteryTime . ')';
			$html .= '<i class="icon divers-calendar2" title="' . $text . '"></i><span> (' . $batterySince . 'j)</span>';
		} else {
			$html .= '<i class="icon divers-calendar2" title="' . __('Pas de date de changement de pile(s) renseignée', __FILE__) . '"></i>';
		}
		$html .= '</span>';
		if ($this->getConfiguration('battery_type', '') != '') {
			$html .= '<span class="pull-right" title="' . __('Piles', __FILE__) . '">' . $this->getConfiguration('battery_type', '') . '</span>';
		}
		$html .= '</div>';
		return $html;
	}

	public function checkAndUpdateCmd($_logicalId, $_value, $_updateTime = null) {
		if ($this->getIsEnable() == 0) {
			return false;
		}
		$cmd = is_object($_logicalId) ? $_logicalId : $this->getCmd('info', $_logicalId);
		if (!is_object($cmd)) {
			return false;
		}
		$oldValue = $cmd->execCmd();
		if ($oldValue !== $cmd->formatValue($_value) || $oldValue === '') {
			$cmd->event($_value, $_updateTime);
			return true;
		}
		if ($_updateTime !== null && $_updateTime !== false) {
			if (strtotime($cmd->getCollectDate()) < strtotime($_updateTime)) {
				$cmd->event($_value, $_updateTime);
				return true;
			}
			return false;
		} else if ($cmd->getConfiguration('repeatEventManagement', 'never') == 'always') {
			$cmd->event($_value, $_updateTime);
			return true;
		}
		if ($_updateTime !== false) {
			$cmd->setCache('collectDate', date('Y-m-d H:i:s'));
			$this->setStatus(array('lastCommunication' => date('Y-m-d H:i:s'), 'timeout' => 0));
		}
		return false;
	}

	public function copy($_name) {
		$eqLogicCopy = clone $this;
		$eqLogicCopy->setName($_name);
		$eqLogicCopy->setId('');
		$eqLogicCopy->save();
		foreach (($eqLogicCopy->getCmd()) as $cmd) {
			$cmd->remove();
		}
		$cmd_link = array();
		foreach (($this->getCmd()) as $cmd) {
			$cmdCopy = clone $cmd;
			$cmdCopy->setId('');
			$cmdCopy->setEqLogic_id($eqLogicCopy->getId());
			$cmdCopy->save();
			$cmd_link[$cmd->getId()] = $cmdCopy;
		}
		foreach (($this->getCmd()) as $cmd) {
			if (!isset($cmd_link[$cmd->getId()])) {
				continue;
			}
			if ($cmd->getValue() != '' && isset($cmd_link[$cmd->getValue()])) {
				$cmd_link[$cmd->getId()]->setValue($cmd_link[$cmd->getValue()]->getId());
				$cmd_link[$cmd->getId()]->save();
			}
		}

		$backGraphCmd = $this->getDisplay('backGraph::info', 0);
		if ($backGraphCmd != 0 && isset($cmd_link[$backGraphCmd])) {
			$eqLogicCopy->setDisplay('backGraph::info', $cmd_link[$backGraphCmd]->getId());
			$eqLogicCopy->save(true);
		}
		return $eqLogicCopy;
	}

	public function getTableName() {
		return 'eqLogic';
	}

	public function hasOnlyEventOnlyCmd() {
		return true;
	}

	public function preToHtml($_version = 'dashboard', $_default = array(), $_noCache = false) {
		global $JEEDOM_INTERNAL_CONFIG;
		if ($_version == '') {
			throw new Exception(__('La version demandée ne peut pas être vide (mobile, dashboard ou scénario)', __FILE__));
		}
		if (!$this->hasRight('r') || !$this->getIsEnable()) {
			return '';
		}
		$translate_category = '';
		foreach ($JEEDOM_INTERNAL_CONFIG['eqLogic']['category'] as $key => $value) {
			if ($this->getCategory($key, 0) == 1) {
				$translate_category .= $value['name'] . ',';
			}
		}
		$translate_category = trim($translate_category, ',');
		$name_display = $this->getName();
		$uid = 'eqLogic' . $this->getId() . self::UIDDELIMITER . mt_rand() . self::UIDDELIMITER;
		$replace = array(
			'#id#' => $this->getId(),
			'#name#' => $this->getName(),
			'#name_display#' => $name_display,
			'#eqLink#' => $this->getLinkToConfiguration(),
			'#category#' => $this->getPrimaryCategory(),
			'#translate_category#' => $translate_category,
			'#style#' => '',
			'#logicalId#' => $this->getLogicalId(),
			'#object_name#' => (is_object($this->getObject())) ? $this->getObject()->getName() : __('Aucun', __FILE__),
			'#height#' => $this->getDisplay('height', 'auto'),
			'#width#' => $this->getDisplay('width', 'auto'),
			'#uid#' => $uid,
			'#refresh_id#' => '',
			'#version#' => $_version,
			'#alert_name#' => '',
			'#alert_icon#' => '',
			'#eqType#' => $this->getEqType_name(),
			'#custom_layout#' => ($this->widgetPossibility('custom::layout')) ? 'allowLayout' : '',
			'#tags#' => $this->getTags(),
			'#generic_type#' => $this->getGenericType(),
			'#isVerticalAlign#' => (config::byKey('interface::advance::vertCentering', 'core', 0) == 1) ? 'verticalAlign' : '',
			'#class#' => '',
			'#divGraphInfo#' => '',
			'#panelLink#' => '',
		);
		$_version = jeedom::versionAlias($_version);
		if ($this->getConfiguration('panelLink') != '') {
			if ($_version == 'dashboard') {
				$replace['#panelLink#'] = 'index.php?v=d&m=' . $this->getEqType_name() . '&p=' . $this->getConfiguration('panelLink');
			} else {
				$replace['#panelLink#'] = 'index.php?v=m&m=' . $this->getEqType_name() . '&p=' . $this->getConfiguration('panelLink');
			}
		}

		//Automatic width when first displayed:
		if ($replace['#width#'] == 'auto') {
			$replace['#width#'] = '230px';
		}

		if ($this->getAlert() != '') {
			$alert = $this->getAlert();
			$replace['#alert_name#'] = $alert['name'];
			$replace['#alert_icon#'] = $alert['icon'];
			$replace['#background-color#'] = $alert['color'];
		}
		$refresh_cmd = $this->getCmd('action', 'refresh');
		if (!is_object($refresh_cmd)) {
			foreach ($this->getCmd('action') as $cmd) {
				if ($cmd->getConfiguration('isRefreshCmd') == 1) {
					$refresh_cmd = $cmd;
					break;
				}
			}
		}
		if (is_object($refresh_cmd) && $refresh_cmd->getIsVisible() == 1) {
			$replace['#refresh_id#'] = $refresh_cmd->getId();
		}

		//Custom parameter css class:
		if (is_array($this->getDisplay('parameters')) && count($this->getDisplay('parameters')) > 0) {
			foreach ($this->getDisplay('parameters') as $key => $value) {
				if ($key == $_version . '_class') {
					$replace['#class#'] = $value;
					continue;
				}
				$replace['#' . $key . '#'] = $value;
			}
		}
		$replace['#style#'] = trim($replace['#style#'], ';');
		if (is_array($this->widgetPossibility('parameters')) && count($this->widgetPossibility('parameters')) > 0) {
			foreach ($this->widgetPossibility('parameters') as $pKey => $parameter) {
				if (!isset($parameter['allow_displayType']) || !isset($parameter['type'])) {
					continue;
				}
				if (is_array($parameter['allow_displayType']) && !in_array($_version, $parameter['allow_displayType'])) {
					continue;
				}
				if ($parameter['allow_displayType'] === false) {
					continue;
				}
				$default = '';
				if (isset($parameter['default'])) {
					$default = $parameter['default'];
				}
				if ($this->getDisplay('advanceWidgetParameter' . $pKey . $_version . '-default', 1) == 1) {
					$replace['#' . $pKey . '#'] = $default;
					continue;
				}
				switch ($parameter['type']) {
					case 'color':
						if ($this->getDisplay('advanceWidgetParameter' . $pKey . $_version . '-transparent', 0) == 1) {
							$replace['#' . $pKey . '#'] = 'transparent';
						} else {
							$replace['#' . $pKey . '#'] = $this->getDisplay('advanceWidgetParameter' . $pKey . $_version, $default);
						}
						break;
					default:
						$replace['#' . $pKey . '#'] = $this->getDisplay('advanceWidgetParameter' . $pKey . $_version, $default);
						break;
				}
			}
		}

		//History graph:
		if ($this->getDisplay('backGraph::info', 0) != 0 && is_object(cmd::byId($this->getDisplay('backGraph::info')))) {
			$doNotHighlightGraphCmd = (config::byKey('interface::advance::doNotHighlightGraphCmd') == 1) ? 'true' : 'false';
			$replace['#divGraphInfo#'] = '<div class="eqlogicbackgraph" data-cmdid="' . $this->getDisplay('backGraph::info') . '" data-format="' . $this->getDisplay('backGraph::format', 'day') . '" data-type="' . $this->getDisplay('backGraph::type', 'areaspline') . '" data-color="' . $this->getDisplay('backGraph::color', '#4572A7') . '"></div><script>jeedom.eqLogic.initGraphInfo("' . $uid . '", ' . $doNotHighlightGraphCmd . ')</script>';
			$height = $this->getDisplay('backGraph::height', '0');
			if ($height != '0') {
				//$replace['#isVerticalAlign#'] = 0;
				$replace['#divGraphInfo#'] = str_replace('data-cmdid=', 'style="height:' . $height . 'px;" data-cmdid=', $replace['#divGraphInfo#']);
				$replace['#divGraphInfo#'] = str_replace('eqlogicbackgraph', 'eqlogicbackgraph fixedbackgraph', $replace['#divGraphInfo#']);
			}
		}
		return $replace;
	}

	public function toHtml($_version = 'dashboard') {
		$replace = $this->preToHtml($_version);
		if (!is_array($replace)) {
			return $replace;
		}
		$_version = jeedom::versionAlias($_version);
		$replace['#calledFrom#'] = __CLASS__;
		switch ($this->getDisplay('layout::' . $_version)) {
			case 'table':
				$replace['#eqLogic_class#'] = 'eqLogic_layout_table';
				$table = self::generateHtmlTable($this->getDisplay('layout::' . $_version . '::table::nbLine', 1), $this->getDisplay('layout::' . $_version . '::table::nbColumn', 1), $this->getDisplay('layout::' . $_version . '::table::parameters'));
				foreach ($this->getCmd(null, null, true) as $cmd) {
					if (isset($replace['#refresh_id#']) && $cmd->getId() == $replace['#refresh_id#']) {
						continue;
					}
					$tag = '#cmd::' . $this->getDisplay('layout::' . $_version . '::table::cmd::' . $cmd->getId() . '::line', 1) . '::' . $this->getDisplay('layout::' . $_version . '::table::cmd::' . $cmd->getId() . '::column', 1) . '#';
					if ($cmd->getDisplay('forceReturnLineBefore', 0) == 1) {
						$table['tag'][$tag] .= '<div class="break"></div>';
					}
					$table['tag'][$tag] .= $cmd->toHtml($_version, '');
					if ($cmd->getDisplay('forceReturnLineAfter', 0) == 1) {
						$table['tag'][$tag] .= '<div class="break"></div>';
					}
				}
				$replace['#cmd#'] = template_replace($table['tag'], $table['html']);
				break;
			default:
				$replace['#eqLogic_class#'] = 'eqLogic_layout_default';
				$cmd_html = '';
				foreach ($this->getCmd(null, null, true) as $cmd) {
					if (isset($replace['#refresh_id#']) && $cmd->getId() == $replace['#refresh_id#']) {
						continue;
					}
					if ($_version == 'dashboard' && $cmd->getDisplay('forceReturnLineBefore', 0) == 1) {
						$cmd_html .= '<div class="break"></div>';
					}
					$cmd_html .= $cmd->toHtml($_version, '');
					if ($_version == 'dashboard' && $cmd->getDisplay('forceReturnLineAfter', 0) == 1) {
						$cmd_html .= '<div class="break"></div>';
					}
				}
				$replace['#cmd#'] = $cmd_html;
				break;
		}
		if (!isset(self::$_templateArray[$_version])) {
			self::$_templateArray[$_version] = getTemplate('core', $_version, 'eqLogic');
		}
		return $this->postToHtml($_version, template_replace($replace, self::$_templateArray[$_version]));
	}

	public function postToHtml($_version, $_html) {
		return $_html;
	}

	public function emptyCacheWidget() {
	}

	public function getAlert() {
		global $JEEDOM_INTERNAL_CONFIG;
		$hasAlert = '';
		$maxLevel = 0;
		foreach ($JEEDOM_INTERNAL_CONFIG['alerts'] as $key => $data) {
			if ($this->getStatus($key, 0) != 0 && $JEEDOM_INTERNAL_CONFIG['alerts'][$key]['level'] > $maxLevel) {
				$hasAlert = $data;
				$maxLevel = $JEEDOM_INTERNAL_CONFIG['alerts'][$key]['level'];
			}
		}
		return $hasAlert;
	}

	public function getMaxCmdAlert() {
		$return = 'none';
		$max = 0;
		global $JEEDOM_INTERNAL_CONFIG;
		foreach ($this->getCmd('info') as $cmd) {
			$cmdLevel = $cmd->getCache('alertLevel');
			if (!isset($JEEDOM_INTERNAL_CONFIG['alerts'][$cmdLevel])) {
				continue;
			}
			if ($JEEDOM_INTERNAL_CONFIG['alerts'][$cmdLevel]['level'] > $max) {
				$return = $cmdLevel;
				$max = $JEEDOM_INTERNAL_CONFIG['alerts'][$cmdLevel]['level'];
			}
		}
		return $return;
	}

	public function getShowOnChild() {
		return false;
	}

	public function remove() {
		foreach (($this->getCmd()) as $cmd) {
			$cmd->remove();
		}
		viewData::removeByTypeLinkId('eqLogic', $this->getId());
		dataStore::removeByTypeLinkId('eqLogic', $this->getId());
		cache::delete('eqLogicCacheAttr' . $this->getId());
		cache::delete('eqLogicStatusAttr' . $this->getId());
		jeedom::addRemoveHistory(array('id' => $this->getId(), 'name' => $this->getHumanName(), 'date' => date('Y-m-d H:i:s'), 'type' => 'eqLogic'));
		return DB::remove($this);
	}

	public function save($_direct = false) {
		if ($this->getName() == '') {
			throw new Exception(__('Le nom de l\'équipement ne peut pas être vide :', __FILE__) . ' ' . print_r($this, true));
		}
		if ($this->getChanged()) {
			if ($this->getId() != '') {
				$this->setConfiguration('updatetime', date('Y-m-d H:i:s'));
			} else {
				$this->setConfiguration('createtime', date('Y-m-d H:i:s'));
				$this->setDisplay('backGraph::info', 0);
			}
			if ($this->getDisplay('layout::dashboard') != 'table') {
				$displays = $this->getDisplay();
				foreach ($displays as $key => $value) {
					if (strpos($key, 'layout::') === 0) {
						$this->setDisplay($key, null);
						continue;
					}
				}
			} else {

				$cmd_ids = array();
				foreach (array('dashboard') as $key) {
					if ($this->getDisplay('layout::' . $key . '::table::parameters') == '') {
						$this->setDisplay('layout::' . $key . '::table::parameters', array('center' => 1, 'styletd' => 'padding:3px;'));
					}
					if ($this->getDisplay('layout::' . $key) == 'table') {
						if ($this->getDisplay('layout::' . $key . '::table::nbLine') == '') {
							$this->setDisplay('layout::' . $key . '::table::nbLine', 1);
						}
						if ($this->getDisplay('layout::' . $key . '::table::nbColumn') == '') {
							$this->setDisplay('layout::' . $key . '::table::nbColumn', 1);
						}
					}
					foreach (($this->getCmd()) as $cmd) {
						$cmd_ids[$cmd->getId()] = $cmd->getId();
						if ($this->getDisplay('layout::' . $key . '::table::cmd::' . $cmd->getId() . '::line') == '' && $cmd->getDisplay('layout::' . $key . '::table::cmd::line') != '') {
							$this->setDisplay('layout::' . $key . '::table::cmd::' . $cmd->getId() . '::line', $cmd->getDisplay('layout::' . $key . '::table::cmd::line'));
						}
						if ($this->getDisplay('layout::' . $key . '::table::cmd::' . $cmd->getId() . '::column') == '' && $cmd->getDisplay('layout::' . $key . '::table::cmd::column') != '') {
							$this->setDisplay('layout::' . $key . '::table::cmd::' . $cmd->getId() . '::column', $cmd->getDisplay('layout::' . $key . '::table::cmd::column'));
						}
						if ($this->getDisplay('layout::' . $key . '::table::cmd::' . $cmd->getId() . '::line', 1) > $this->getDisplay('layout::' . $key . '::table::nbLine', 1)) {
							$this->setDisplay('layout::' . $key . '::table::cmd::' . $cmd->getId() . '::line', $this->getDisplay('layout::' . $key . '::table::nbLine', 1));
						}
						if ($this->getDisplay('layout::' . $key . '::table::cmd::' . $cmd->getId() . '::column', 1) > $this->getDisplay('layout::' . $key . '::table::nbColumn', 1)) {
							$this->setDisplay('layout::' . $key . '::table::cmd::' . $cmd->getId() . '::column', $this->getDisplay('layout::' . $key . '::table::nbColumn', 1));
						}
						if ($this->getDisplay('layout::' . $key . '::table::cmd::' . $cmd->getId() . '::line') == '') {
							$this->setDisplay('layout::' . $key . '::table::cmd::' . $cmd->getId() . '::line', 1);
						}
						if ($this->getDisplay('layout::' . $key . '::table::cmd::' . $cmd->getId() . '::column') == '') {
							$this->setDisplay('layout::' . $key . '::table::cmd::' . $cmd->getId() . '::column', 1);
						}
					}
				}
				$displays = $this->getDisplay();
				foreach ($displays as $key => $value) {
					preg_match_all('/::cmd::(.*?)::/m', $key, $matches, PREG_SET_ORDER, 0);
					if (isset($matches[1]) && !isset($cmd_ids[$matches[1]])) {
						$this->setDisplay($key, null);
					}
				}
			}
		}
		$newEqlogic = ($this->getId() == '');
		DB::save($this, $_direct);
		if ($this->_needRefreshWidget) {
			$this->_needRefreshWidget = false;
			$this->refreshWidget();
		}
		if ($this->_batteryUpdated) {
			$this->_batteryUpdated = false;
			$this->batteryStatus();
		}
		if ($this->_timeoutUpdated) {
			$this->_timeoutUpdated = false;
			if ($this->getTimeout() == null) {
				foreach (message::byPluginLogicalId('core', 'noMessage' . $this->getId()) as $message) {
					$message->remove();
				}
				$this->setStatus('timeout', 0);
			} else {
				$this->checkAlive();
			}
		}
		if ($newEqlogic) {
			jeedom::event('new_eqLogic', false, array('id' => $this->getId(), 'name' => $this->getName(), 'eqType' => $this->getEqType_name()));
		}
	}

	public function refresh() {
		DB::refresh($this);
	}

	public function getLinkToConfiguration() {
		if (isset($_SESSION['user']) && is_object($_SESSION['user']) && !isConnect('admin')) {
			return '#';
		}
		return 'index.php?v=d&p=' . $this->getEqType_name() . '&m=' . $this->getEqType_name() . '&id=' . $this->getId();
	}

	public function getHumanName($_tag = false, $_prettify = false) {
		$name = '';
		$object = $this->getObject();
		if (is_object($object)) {
			$name .= $object->getHumanName($_tag, $_prettify);
		} else {
			if ($_tag) {
				$name .= '<span class="label labelObjectHuman" style="text-shadow : none;">' . __('Aucun', __FILE__) . '</span>';
			} else {
				$name .= '[' . __('Aucun', __FILE__) . ']';
			}
		}
		if ($_prettify) {
			$name .= '<br/><strong>';
		}
		if ($_tag) {
			$name .= ' ' . $this->getName();
		} else {
			$name .= '[' . $this->getName() . ']';
		}
		if ($_prettify) {
			$name .= '</strong>';
		}
		return $name;
	}

	public function getPrimaryCategory() {
		foreach ($this->category as $cat => $value) {
			if ($value == 1) {
				return ($cat != 'default' ? $cat : '');
			}
		}
		return '';
	}

	public function displayDebug($_message) {
		if ($this->getDebug()) {
			echo $_message . "\n";
		}
	}

	public function batteryStatus($_pourcent = '', $_datetime = '') {
		if ($this->getConfiguration('noBatterieCheck', 0) == 1) {
			return;
		}
		$currentpourcent = null;
		if ($_pourcent === '') {
			$_pourcent = $this->getStatus('battery');
			$_datetime = $this->getStatus('batteryDatetime');
		} else {
			$currentpourcent = $this->getStatus('battery');
		}
		if ($_pourcent > 100) {
			$_pourcent = 100;
		}
		if ($_pourcent < 0) {
			$_pourcent = 0;
		}
		if ($_pourcent > 90 && $_pourcent > ($this->getStatus('battery', 0) * 1.5)) {
			$this->setConfiguration('batterytime', date('Y-m-d H:i:s'));
			$this->save(true);
		}

		$warning_threshold = $this->getConfiguration('battery_warning_threshold', config::byKey('battery::warning'));
		$danger_threshold = $this->getConfiguration('battery_danger_threshold', config::byKey('battery::danger'));
		if ($_pourcent !== '' && $_pourcent < $danger_threshold && strtotime($this->getStatus('batteryDatetime')) + 7 * 24 * 3600 > strtotime('now')) {
			if ($currentpourcent < $danger_threshold) {
				return;
			}
			$prevStatus = $this->getStatus('batterydanger', 0);
			$message = 'L\'équipement ' . $this->getEqType_name() . ' ' . $this->getHumanName() . ' a moins de ' . $danger_threshold . '% de batterie (niveau danger avec ' . $_pourcent . '% de batterie)';
			if ($this->getConfiguration('battery_type') != '') {
				$message .= ' (' . $this->getConfiguration('battery_type') . ')';
			}
			$action = '<a href="/' . $this->getLinkToConfiguration() . '">' . __('Equipement', __FILE__) . '</a>';
			$logicalId = 'lowBattery' . $this->getId();
			$this->setStatus('batterydanger', 1);
			if ($prevStatus == 0) {
				if (config::byKey('alert::addMessageOnBatterydanger') == 1) {
					message::add($this->getEqType_name(), $message, $action, $logicalId);
				}
				$cmds = explode(('&&'), config::byKey('alert::batterydangerCmd'));
				if (count($cmds) > 0 && trim(config::byKey('alert::batterydangerCmd')) != '') {
					foreach ($cmds as $id) {
						$cmd = cmd::byId(str_replace('#', '', $id));
						if (is_object($cmd)) {
							$cmd->execCmd(array(
								'title' => '[' . config::byKey('name', 'core', 'JEEDOM') . '] ' . $message,
								'message' => config::byKey('name', 'core', 'JEEDOM') . ' : ' . $message,
							));
						}
					}
				}
			}
		} else if ($_pourcent !== '' && $_pourcent < $warning_threshold) {
			if ($currentpourcent < $warning_threshold && strtotime($this->getStatus('batteryDatetime')) + 7 * 24 * 3600 > strtotime('now')) {
				return;
			}
			$prevStatus = $this->getStatus('batterywarning', 0);
			$message = 'L\'équipement ' . $this->getEqType_name() . ' ' . $this->getHumanName() . ' a moins de ' . $warning_threshold . '% de batterie (niveau warning avec ' . $_pourcent . '% de batterie)';
			if ($this->getConfiguration('battery_type') != '') {
				$message .= ' (' . $this->getConfiguration('battery_type') . ')';
			}
			$action = '<a href="/' . $this->getLinkToConfiguration() . '">' . __('Equipement', __FILE__) . '</a>';
			$logicalId = 'warningBattery' . $this->getId();
			$this->setStatus('batterywarning', 1);
			$this->setStatus('batterydanger', 0);
			if ($prevStatus == 0) {
				if (config::byKey('alert::addMessageOnBatterywarning') == 1) {
					message::add($this->getEqType_name(), $message, $action, $logicalId);
				}
				$cmds = explode(('&&'), config::byKey('alert::batterywarningCmd'));
				if (count($cmds) > 0 && trim(config::byKey('alert::batterywarningCmd')) != '') {
					foreach ($cmds as $id) {
						$cmd = cmd::byId(str_replace('#', '', $id));
						if (is_object($cmd)) {
							$cmd->execCmd(array(
								'title' => __('[' . config::byKey('name', 'core', 'JEEDOM') . ']', __FILE__) . ' ' . $message,
								'message' => config::byKey('name', 'core', 'JEEDOM') . ' : ' . $message,
							));
						}
					}
				}
			}
		} else {
			message::removeByPluginLogicalId($this->getEqType_name(), 'warningBattery' . $this->getId());
			message::removeByPluginLogicalId($this->getEqType_name(), 'lowBattery' . $this->getId());
			$this->setStatus('batterydanger', 0);
			$this->setStatus('batterywarning', 0);
		}

		$this->setStatus(array('battery' => $_pourcent, 'batteryDatetime' => ($_datetime != '') ? $_datetime : date('Y-m-d H:i:s')));
	}

	public function refreshWidget() {
		$this->_needRefreshWidget = false;
		event::add('eqLogic::update', array('eqLogic_id' => $this->getId(), 'visible' => $this->getIsVisible(), 'enable' => $this->getIsEnable()));
	}

	public function hasRight($_right, $_user = null) {
		if ($_user != null) {
			if ($_user->getProfils() == 'admin' || $_user->getProfils() == 'user') {
				return true;
			}
			if (strpos($_user->getRights('eqLogic' . $this->getId()), $_right) !== false) {
				return true;
			}
			return false;
		}
		if (!isConnect()) {
			return false;
		}
		if (isConnect('admin') || isConnect('user')) {
			return true;
		}
		if (strpos($_SESSION['user']->getRights('eqLogic' . $this->getId()), $_right) !== false) {
			return true;
		}
		return false;
	}

	public static function migrateEqlogic($_sourceId, $_targetId, $_mode = 'replace') {
		$sourceEq = eqLogic::byId($_sourceId);
		if (!is_object($sourceEq)) {
			throw new Exception(__('L\'équipement source n\'existe pas', __FILE__));
		}
		$targetEq = eqLogic::byId($_targetId);
		if (!is_object($sourceEq)) {
			throw new Exception(__('L\'équipement cible n\'existe pas', __FILE__));
		}

		$migrateDisplayValues = [
			'parameters' => array(),
			'height' => '',
			'width' => '',
			'height' => '',
			'backGraph::format' => '',
			'backGraph::type' => '',
			'backGraph::color' => '',
			'backGraph::height' => '',
			'layout::dashboard' => '',
			'layout::dashboard::table::nbLine' => '',
			'layout::dashboard::table::nbColumn' => ''
		];

		$migrateConfigurationValues = [
			'autorefresh' => '',
			'icon' => '',
			'battery_type' => '',
			'battery_danger_threshold' => '',
			'battery_warning_threshold' => '',
			//'batterytime' => '',
		];

		try {
			//properties:
			$targetEq->setObject_id($sourceEq->getObject_id());
			$targetEq->setIsVisible($sourceEq->getIsVisible());
			$targetEq->setIsEnable($sourceEq->getIsEnable());
			$targetEq->setOrder($sourceEq->getOrder());
			$targetEq->setGenericType($sourceEq->getGenericType());
			$targetEq->setTimeout($sourceEq->getTimeout());
			$targetEq->setComment($sourceEq->getComment());
			$targetEq->setTags($sourceEq->getTags());


			//categories:
			foreach (jeedom::getConfiguration('eqLogic:category') as $key => $value) {
				$targetEq->setCategory($key, $sourceEq->getCategory($key, '0'));
			}


			//display:
			foreach ($migrateDisplayValues as $key => $value) {
				if (is_array($value)) {
					if (count($sourceEq->getDisplay($key, $value)) > 0) {
						$targetEq->setDisplay($key, $sourceEq->getDisplay($key, $value));
					} else {
						$targetEq->setDisplay($key, $value);
					}
				}
				if (is_string($value)) {
					if ($sourceEq->getDisplay($key) != $value) {
						$targetEq->setDisplay($key, $sourceEq->getDisplay($key, $value));
					} else {
						$targetEq->setDisplay($key, null);
					}
				}
			}

			//configuration:
			foreach ($migrateConfigurationValues as $key => $value) {
				if (is_array($value)) {
					if (count($sourceEq->getConfiguration($key, $value)) > 0) {
						$targetEq->setConfiguration($key, $sourceEq->getConfiguration($key, $value));
					} else {
						$targetEq->setConfiguration($key, $value);
					}
				}
				if (is_string($value)) {
					if ($sourceEq->getConfiguration($key, $value) != $value) {
						$targetEq->setConfiguration($key, $sourceEq->getConfiguration($key, $value));
					} else {
						$targetEq->setConfiguration($key, null);
					}
				}
			}

			$targetEq->save();

			if ($_mode == 'replace') {
				//Designs:
				$sql = 'UPDATE `plan` SET `link_id` = ' . $targetEq->getId() . ' WHERE `link_type` = \'eqLogic\' AND `link_id` = ' . $sourceEq->getId();
				DB::Prepare($sql, array(), DB::FETCH_TYPE_ALL, PDO::FETCH_CLASS, __CLASS__);

				//Views:
				$sql = 'UPDATE `viewData` SET `link_id` = ' . $targetEq->getId() . ' WHERE `type` = \'eqLogic\' AND `link_id` = ' . $sourceEq->getId();
				DB::Prepare($sql, array(), DB::FETCH_TYPE_ALL, PDO::FETCH_CLASS, __CLASS__);
			}
			return $targetEq;
		} catch (Exception $e) {
			throw new Exception(__('Erreur lors de la migration d\'équipement', __FILE__) . ' : ' . $e->getMessage());
		}
	}

	public function import($_configuration, $_dontRemove = false) {
		$cmdClass = $this->getEqType_name() . 'Cmd';
		if (isset($_configuration['configuration'])) {
			foreach ($_configuration['configuration'] as $key => $value) {
				$this->setConfiguration($key, $value);
			}
		}
		if (isset($_configuration['category'])) {
			foreach ($_configuration['category'] as $key => $value) {
				$this->setCategory($key, $value);
			}
		}
		$cmd_order = 0;
		$link_cmds = array();
		$link_actions = array();
		$arrayToRemove = [];
		if (isset($_configuration['commands'])) {
			foreach (($this->getCmd()) as $eqLogic_cmd) {
				$exists = 0;
				foreach ($_configuration['commands'] as $command) {
					if (isset($command['logicalId']) && $command['logicalId'] == $eqLogic_cmd->getLogicalId()) {
						$exists++;
					}
				}
				if ($exists < 1) {
					$arrayToRemove[] = $eqLogic_cmd;
				}
			}
			if (!$_dontRemove) {
				foreach ($arrayToRemove as $cmdToRemove) {
					try {
						$cmdToRemove->remove();
					} catch (Exception $e) {
					}
				}
			}
			foreach ($_configuration['commands'] as $command) {
				$cmd = null;
				foreach (($this->getCmd()) as $liste_cmd) {
					if ((isset($command['logicalId']) && $liste_cmd->getLogicalId() == $command['logicalId'])
						|| (isset($command['name']) && $liste_cmd->getName() == $command['name'])
					) {
						$cmd = $liste_cmd;
						break;
					}
				}
				try {
					if ($cmd === null || !is_object($cmd)) {
						$cmd = new $cmdClass();
						$cmd->setOrder($cmd_order);
						$cmd->setEqLogic_id($this->getId());
					} else {
						$command['name'] = $cmd->getName();
						if (isset($command['display'])) {
							unset($command['display']);
						}
					}
					utils::a2o($cmd, $command);
					$cmd->setConfiguration('logicalId', $cmd->getLogicalId());
					$cmd->save();
					if (isset($command['value'])) {
						$link_cmds[$cmd->getId()] = $command['value'];
					}
					if (isset($command['configuration']['updateCmdId'])) {
						$link_actions[$cmd->getId()] = $command['configuration']['updateCmdId'];
					}
					$cmd_order++;
				} catch (Exception $exc) {
				}
			}
		}
		if (count($link_cmds) > 0) {
			foreach (($this->getCmd()) as $eqLogic_cmd) {
				foreach ($link_cmds as $cmd_id => $link_cmd) {
					if ($link_cmd == $eqLogic_cmd->getName()) {
						$cmd = cmd::byId($cmd_id);
						if (is_object($cmd)) {
							$cmd->setValue($eqLogic_cmd->getId());
							$cmd->save();
						}
					}
				}
			}
		}
		if (count($link_actions) > 0) {
			foreach (($this->getCmd()) as $eqLogic_cmd) {
				foreach ($link_actions as $cmd_id => $link_action) {
					if ($link_action == $eqLogic_cmd->getName()) {
						$cmd = cmd::byId($cmd_id);
						if (is_object($cmd)) {
							$cmd->setConfiguration('updateCmdId', $eqLogic_cmd->getId());
							$cmd->save();
						}
					}
				}
			}
		}
		$this->save();
	}

	public function export($_withCmd = true) {
		$eqLogic = clone $this;
		$eqLogic->setId('');
		$eqLogic->setLogicalId('');
		$eqLogic->setObject_id('');
		$eqLogic->setIsEnable('');
		$eqLogic->setIsVisible('');
		$eqLogic->setTimeout('');
		$eqLogic->setOrder('');
		$eqLogic->setConfiguration('nerverFail', '');
		$eqLogic->setConfiguration('noBatterieCheck', '');
		$return = utils::o2a($eqLogic);
		foreach ($return as $key => $value) {
			if (is_array($value)) {
				foreach ($value as $key2 => $value2) {
					if ($value2 == '') {
						unset($return[$key][$key2]);
					}
				}
			} else {
				if ($value == '') {
					unset($return[$key]);
				}
			}
		}
		if (isset($return['configuration']) && count($return['configuration']) == 0) {
			unset($return['configuration']);
		}
		if (isset($return['display']) && count($return['display']) == 0) {
			unset($return['display']);
		}
		if ($_withCmd) {
			$return['commands'] = array();
			foreach (($this->getCmd()) as $cmd) {
				$return['commands'][] = $cmd->export();
			}
		}
		return $return;
	}

	public function widgetPossibility($_key = '', $_default = true) {
		$class = new ReflectionClass($this->getEqType_name());
		$method_toHtml = $class->getMethod('toHtml');
		$return = array();
		if ($method_toHtml->class == 'eqLogic' || $this->getDisplay('widgetTmpl', 1) == 0) {
			if (strpos($_key, 'custom') !== false) {
				return true;
			}
			$return['custom'] = true;
		} else {
			$return['custom'] = false;
		}
		$class = $this->getEqType_name();
		if (property_exists($class, '_widgetPossibility')) {
			$return = $class::$_widgetPossibility;
			if ($_key != '') {
				if (isset($return[$_key])) {
					return $return[$_key];
				}
				$keys = explode('::', $_key);
				foreach ($keys as $k) {
					if (!isset($return[$k])) {
						return false;
					}
					if (is_array($return[$k])) {
						$return = $return[$k];
					} else {
						return $return[$k];
					}
				}
				if (is_array($return) && strpos($_key, 'custom') !== false) {
					return $_default;
				}
				return $return;
			}
		}
		if ($_key != '') {
			if (isset($return['custom']) && !isset($return[$_key])) {
				return $return['custom'];
			}
			return (isset($return[$_key])) ? $return[$_key] : $_default;
		}
		return $return;
	}

	public function toArray() {
		$return = utils::o2a($this, true);
		$return['status'] = $this->getStatus();
		$return['cache'] = $this->getCache();
		return $return;
	}

	public function getImage() {
		$plugin = plugin::byId($this->getEqType_name());
		return $plugin->getPathImgIcon();
	}

	public function getLinkData(&$_data = array('node' => array(), 'link' => array()), $_level = 0, $_drill = null) {
		if ($_drill === null) {
			$_drill = config::byKey('graphlink::eqLogic::drill');
		}
		if (isset($_data['node']['eqLogic' . $this->getId()])) {
			return;
		}
		$_level++;
		if ($_level > $_drill) {
			return $_data;
		}
		$_data['node']['eqLogic' . $this->getId()] = array(
			'id' => 'eqLogic' . $this->getId(),
			'name' => $this->getName(),
			'type' => __('Equipement', __FILE__),
			'width' => 60,
			'height' => 60,
			'fontweight' => ($_level == 1) ? 'bold' : 'normal',
			'image' => $this->getImage(),
			'isActive' => $this->getIsEnable(),
			'title' => $this->getHumanName(),
			'url' => $this->getLinkToConfiguration(),
		);
		$use = $this->getUse();
		$usedBy = $this->getUsedBy();
		addGraphLink($this, 'eqLogic', $this->getCmd(), 'cmd', $_data, $_level, $_drill, array('dashvalue' => '1,0', 'lengthfactor' => 0.6));
		addGraphLink($this, 'eqLogic', $use['cmd'], 'cmd', $_data, $_level, $_drill);
		addGraphLink($this, 'eqLogic', $use['scenario'], 'scenario', $_data, $_level, $_drill);
		addGraphLink($this, 'eqLogic', $use['eqLogic'], 'eqLogic', $_data, $_level, $_drill);
		addGraphLink($this, 'eqLogic', $use['dataStore'], 'dataStore', $_data, $_level, $_drill);
		foreach ($usedBy['plugin'] as $key => $value) {
			addGraphLink($this, 'eqLogic', $value, $key, $_data, $_level, $_drill);
		}
		addGraphLink($this, 'eqLogic', $usedBy['cmd'], 'cmd', $_data, $_level, $_drill);
		addGraphLink($this, 'eqLogic', $usedBy['scenario'], 'scenario', $_data, $_level, $_drill);
		addGraphLink($this, 'eqLogic', $usedBy['eqLogic'], 'eqLogic', $_data, $_level, $_drill);
		addGraphLink($this, 'eqLogic', $usedBy['interactDef'], 'interactDef', $_data, $_level, $_drill, array('dashvalue' => '2,6', 'lengthfactor' => 0.6));
		addGraphLink($this, 'eqLogic', $usedBy['plan'], 'plan', $_data, $_level, $_drill, array('dashvalue' => '2,6', 'lengthfactor' => 0.6));
		addGraphLink($this, 'eqLogic', $usedBy['plan3d'], 'plan3d', $_data, $_level, $_drill, array('dashvalue' => '2,6', 'lengthfactor' => 0.6));
		addGraphLink($this, 'eqLogic', $usedBy['view'], 'view', $_data, $_level, $_drill, array('dashvalue' => '2,6', 'lengthfactor' => 0.6));
		if (!isset($_data['object' . $this->getObject_id()])) {
			addGraphLink($this, 'eqLogic', $this->getObject(), 'object', $_data, $_level, $_drill, array('dashvalue' => '1,0', 'lengthfactor' => 0.6));
		}
		return $_data;
	}

	public function getUse() {
		$json = jeedom::fromHumanReadable(json_encode(utils::o2a($this)));
		return jeedom::getTypeUse($json);
	}

	public function getUsedBy($_array = false) {
		$return = array('cmd' => array(), 'eqLogic' => array(), 'interactDef' => array(), 'scenario' => array(), 'plan' => array(), 'view' => array());
		$return['cmd'] = cmd::searchConfiguration('#eqLogic' . $this->getId() . '#');
		$return['eqLogic'] = self::searchConfiguration(array('#eqLogic' . $this->getId() . '#', '"eqLogic":"' . $this->getId() . '"'));
		$return['interactDef'] = interactDef::searchByUse(array('#eqLogic' . $this->getId() . '#', '"eqLogic":"' . $this->getId() . '"'));
		$return['scenario'] = scenario::searchByUse(array(
			array('action' => 'equipment', 'option' => $this->getId(), 'and' => true),
			array('action' => '#eqLogic' . $this->getId() . '#'),
		));
		$return['view'] = view::searchByUse('eqLogic', $this->getId());
		$return['plan'] = planHeader::searchByUse('eqLogic', $this->getId());
		$return['plan3d'] = plan3dHeader::searchByUse('eqLogic', $this->getId());
		$return['plugin'] = array();
		foreach (plugin::listPlugin(true, false, true, true) as $plugin) {
			if (method_exists($plugin, 'customUsedBy')) {
				$return['plugin'][$plugin] = $plugin::customUsedBy('eqLogic', $this->getId());
			}
		}
		if ($_array) {
			foreach ($return as &$value) {
				$value = utils::o2a($value);
			}
		}
		return $return;
	}

	public static function deadCmdGeneric($_plugin_id) {
		$return = array();
		foreach (eqLogic::byType($_plugin_id) as $eqLogic) {
			$eqLogic_json = json_encode(utils::o2a($eqLogic));
			preg_match_all("/#([0-9]*)#/", $eqLogic_json, $matches);
			foreach ($matches[1] as $cmd_id) {
				if (is_numeric($cmd_id)) {
					if (!cmd::byId(str_replace('#', '', $cmd_id))) {
						$return[] = array(
							'detail' => '<a href="/index.php?v=d&m=' . $eqLogic->getEqType_name() . '&p=' . $eqLogic->getEqType_name() . '&id=' . $eqLogic->getId() . '">' . $eqLogic->getHumanName() . '</a>',
							'help' => __('Action', __FILE__),
							'who' => '#' . $cmd_id . '#'
						);
					}
				}
			}
		}
		return $return;
	}

	public function getUsage() {
		$return = array(
			'automation' => 0,
			'ui' => 0,
			'history' => 0
		);
		foreach ($this->getCmd() as $cmd) {
			$usage = $cmd->getCache(array('usage::automation', 'usage::ui', 'usage::history'));
			if ($usage['usage::automation'] > 0) {
				$return['automation'] += $usage['usage::automation'];
			}
			if ($usage['usage::ui'] > 0) {
				$return['ui'] += $usage['usage::ui'];
			}
			if ($usage['usage::history'] > 0) {
				$return['history'] += $usage['usage::history'];
			}
		}
		return $return;
	}

	/*     * **********************Getteur Setteur*************************** */

	/**
	 *
	 * @return int
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 *
	 * @return string
	 */
	public function getName() {
		return $this->name;
	}

	/**
	 *
	 * @return string
	 */
	public function getLogicalId() {
		return $this->logicalId;
	}

	/**
	 *
	 * @return int
	 */
	public function getObject_id() {
		return $this->object_id;
	}

	/**
	 *
	 * @return jeeObject
	 */
	public function getObject() {
		if ($this->_object === null) {
			$this->setObject(jeeObject::byId($this->object_id));
		}
		return $this->_object;
	}

	/**
	 *
	 * @param jeeObject $_object
	 * @return $this
	 */
	public function setObject($_object) {
		$this->_object = $_object;
		return $this;
	}

	/**
	 *
	 * @return string
	 */
	public function getEqType_name() {
		return $this->eqType_name;
	}

	/**
	 *
	 * @param integer $_default
	 * @return int
	 */
	public function getIsVisible($_default = 0) {
		if ($this->isVisible == '' || !is_numeric($this->isVisible)) {
			return $_default;
		}
		return $this->isVisible;
	}

	/**
	 *
	 * @param integer $_default
	 * @return int
	 */
	public function getIsEnable($_default = 0) {
		if ($this->isEnable == '' || !is_numeric($this->isEnable)) {
			return $_default;
		}
		return $this->isEnable;
	}

	/**
	 * get one or multiple cmd of the eqLogic
	 *
	 * @param string $_type ['action'|'info']
	 * @param string $_logicalId
	 * @param boolean $_visible
	 * @param boolean $_multiple
	 * @return cmd|cmd[]
	 */
	public function getCmd($_type = null, $_logicalId = null, $_visible = null, $_multiple = false) {
		if ($_logicalId !== null) {
			if (isset($this->_cmds[$_logicalId . '.' . $_multiple . '.' . $_type])) {
				return $this->_cmds[$_logicalId . '.' . $_multiple . '.' . $_type];
			}
			$cmds = cmd::byEqLogicIdAndLogicalId($this->id, $_logicalId, $_multiple, $_type, $this);
		} else {
			$cmds = cmd::byEqLogicId($this->id, $_type, $_visible, $this);
		}
		if (is_array($cmds)) {
			foreach ($cmds as $cmd) {
				$cmd->setEqLogic($this);
			}
		} elseif (is_object($cmds)) {
			$cmds->setEqLogic($this);
		}
		if ($_logicalId !== null && is_object($cmds)) {
			$this->_cmds[$_logicalId . '.' . $_multiple . '.' . $_type] = $cmds;
		}
		return $cmds;
	}

	/**
	 * get one or multiple cmd of the eqLogic
	 *
	 * @param string $_type ['action'|'info']
	 * @param string $_generic_type
	 * @param boolean $_visible
	 * @param boolean $_multiple
	 * @return cmd|cmd[]
	 */
	public function getCmdByGenericType($_type = null, $_generic_type = null, $_visible = null, $_multiple = false) {
		if ($_generic_type !== null) {
			if (isset($this->_cmds[$_generic_type . '.' . $_multiple . '.' . $_type])) {
				return $this->_cmds[$_generic_type . '.' . $_multiple . '.' . $_type];
			}
			$cmds = cmd::byEqLogicIdAndGenericType($this->id, $_generic_type, $_multiple, $_type, $this);
		} else {
			$cmds = cmd::byEqLogicId($this->id, $_type, $_visible, $this);
		}
		if (is_array($cmds)) {
			foreach ($cmds as $cmd) {
				$cmd->setEqLogic($this);
			}
		} elseif (is_object($cmds)) {
			$cmds->setEqLogic($this);
		}
		if ($_generic_type !== null && is_object($cmds)) {
			$this->_cmds[$_generic_type . '.' . $_multiple . '.' . $_type] = $cmds;
		}
		return $cmds;
	}

	/**
	 *
	 * @param string $_configuration
	 * @param string $_type ['action'|'info']
	 * @return cmd[]
	 */
	public function searchCmdByConfiguration($_configuration, $_type = null) {
		return cmd::searchConfigurationEqLogic($this->id, $_configuration, $_type);
	}

	public function setId($_id) {
		$this->_changed = utils::attrChanged($this->_changed, $this->id, $_id);
		$this->id = $_id;
		return $this;
	}

	public function setName($_name) {
		$_name = substr(cleanComponanteName($_name), 0, 127);
		$_name = trim($_name);
		if ($_name != $this->name) {
			$this->_needRefreshWidget = true;
			$this->_changed = true;
		}
		$this->name = $_name;
		return $this;
	}

	public function setLogicalId($_logicalId) {
		$this->_changed = utils::attrChanged($this->_changed, $this->logicalId, $_logicalId);
		$this->logicalId = $_logicalId;
		return $this;
	}

	public function setObject_id($object_id = null) {
		$object_id = (!is_numeric($object_id)) ? null : $object_id;
		$this->_changed = utils::attrChanged($this->_changed, $this->object_id, $object_id);
		$this->object_id = $object_id;
		return $this;
	}

	public function setEqType_name($eqType_name) {
		$this->_changed = utils::attrChanged($this->_changed, $this->eqType_name, $eqType_name);
		$this->eqType_name = $eqType_name;
		return $this;
	}

	public function setIsVisible($_isVisible) {
		if ($this->isVisible != $_isVisible) {
			$this->_needRefreshWidget = true;
			$this->_changed = true;
		}
		$this->isVisible = $_isVisible;
		return $this;
	}

	public function setIsEnable($_isEnable) {
		if ($this->isEnable != $_isEnable) {
			$this->_needRefreshWidget = true;
			$this->_changed = true;
			if ($_isEnable) {
				$this->setStatus(array('lastCommunication' => date('Y-m-d H:i:s'), 'timeout' => 0, 'enableDatime' => date('Y-m-d H:i:s')));
			}
		}
		$this->isEnable = $_isEnable;
		return $this;
	}

	public function getConfiguration($_key = '', $_default = '') {
		return utils::getJsonAttr($this->configuration, $_key, $_default);
	}

	public function setConfiguration($_key, $_value) {
		if (in_array($_key, array('battery_warning_threshold', 'battery_danger_threshold'))) {
			if ($this->getConfiguration($_key, '') !== $_value) {
				$this->_batteryUpdated = True;
			}
		}
		$configuration = utils::setJsonAttr($this->configuration, $_key, $_value);
		$this->_changed = utils::attrChanged($this->_changed, $this->configuration, $configuration);
		$this->configuration = $configuration;
		return $this;
	}

	public function getDisplay($_key = '', $_default = '') {
		return utils::getJsonAttr($this->display, $_key, $_default);
	}

	public function setDisplay($_key, $_value) {
		if ($this->getDisplay($_key) !== $_value) {
			$this->_needRefreshWidget = true;
			$this->_changed = true;
		}
		$display = utils::setJsonAttr($this->display, $_key, $_value);
		$this->display = $display;
		return $this;
	}

	public function getTimeout($_default = null) {
		if ($this->timeout == '' || !is_numeric($this->timeout)) {
			return $_default;
		}
		return $this->timeout;
	}

	public function setTimeout($_timeout) {
		if ($_timeout == '' || !is_numeric($_timeout) || $_timeout < 1) {
			$_timeout = null;
		}
		if ($_timeout != $this->getTimeout()) {
			$this->_timeoutUpdated = true;
			$this->_changed = true;
		}
		$this->timeout = $_timeout;
		return $this;
	}

	public function getCategory($_key = '', $_default = '') {
		if ($_key == 'other' && strpos($this->category, "1") === false) {
			return 1;
		}
		return utils::getJsonAttr($this->category, $_key, $_default);
	}

	public function setCategory($_key, $_value) {
		if ($this->getCategory($_key) != $_value) {
			$this->_needRefreshWidget = true;
		}
		$category = utils::setJsonAttr($this->category, $_key, $_value);
		$this->_changed = utils::attrChanged($this->_changed, $this->category, $category);
		$this->category = $category;
		return $this;
	}

	public function getGenericType() {
		return $this->generic_type;
	}

	public function setGenericType($_generic_type) {
		$this->_changed = utils::attrChanged($this->_changed, $this->generic_type, $_generic_type);
		$this->generic_type = $_generic_type;
		return $this;
	}

	public function getComment() {
		return $this->comment;
	}

	public function setComment($_comment) {
		$this->_changed = utils::attrChanged($this->_changed, $this->comment, $_comment);
		$this->comment = $_comment;
		return $this;
	}

	public function getTags() {
		return $this->tags;
	}

	public function setTags($_tags) {
		$_tags = str_replace(array("'", '<', '>'), "", $_tags);
		$this->_changed = utils::attrChanged($this->_changed, $this->tags, $_tags);
		$this->tags = $_tags;
		return $this;
	}

	public function getDebug() {
		return $this->_debug;
	}

	public function setDebug($_debug) {
		if ($_debug) {
			echo "Mode debug activé\n";
		}
		$this->_debug = $_debug;
	}

	/**
	 *
	 * @return int
	 */
	public function getOrder() {
		if ($this->order == '' || !is_numeric($this->order)) {
			return 0;
		}
		return $this->order;
	}

	public function setOrder($_order) {
		$this->_changed = utils::attrChanged($this->_changed, $this->order, $_order);
		$this->order = $_order;
		return $this;
	}

	public function getCache($_key = '', $_default = '') {
		$cache = cache::byKey('eqLogicCacheAttr' . $this->getId())->getValue();
		return utils::getJsonAttr($cache, $_key, $_default);
	}

	public function setCache($_key, $_value = null) {
		cache::set('eqLogicCacheAttr' . $this->getId(), utils::setJsonAttr(cache::byKey('eqLogicCacheAttr' . $this->getId())->getValue(), $_key, $_value));
	}

	public function getStatus($_key = '', $_default = '') {
		$status = cache::byKey('eqLogicStatusAttr' . $this->getId())->getValue();
		return utils::getJsonAttr($status, $_key, $_default);
	}

	public function setStatus($_key, $_value = null) {
		global $JEEDOM_INTERNAL_CONFIG;
		$changed = false;
		if (is_array($_key)) {
			foreach ($_key as $key => $value) {
				if (isset($JEEDOM_INTERNAL_CONFIG['alerts'][$key])) {
					$changed = ($this->getStatus($key) != $value);
				}
				if ($changed) {
					break;
				}
			}
		} else {
			if (isset($JEEDOM_INTERNAL_CONFIG['alerts'][$_key])) {
				$changed = ($this->getStatus($_key) != $_value);
			}
		}
		cache::set('eqLogicStatusAttr' . $this->getId(), utils::setJsonAttr(cache::byKey('eqLogicStatusAttr' . $this->getId())->getValue(), $_key, $_value));
		if ($changed) {
			$this->refreshWidget();
		}
	}

	public function getChanged() {
		return $this->_changed;
	}

	public function setChanged($_changed) {
		$this->_changed = $_changed;
		return $this;
	}
}
