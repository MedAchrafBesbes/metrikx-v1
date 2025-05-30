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

use PragmaRX\Google2FA\Google2FA;

class user {
	/*     * *************************Attributs****************************** */

	private $id;
	private $login;
	private $profils = 'admin';
	private $password;
	private $options;
	private $rights;
	private $enable = 1;
	private $hash;
	private $_changed = false;


	/*     * ***********************Méthodes statiques*************************** */

	public static function byId($_id) {
		$values = array(
			'id' => $_id,
		);
		$sql = 'SELECT ' . DB::buildField(__CLASS__) . '
		FROM user
		WHERE id=:id';
		return DB::Prepare($sql, $values, DB::FETCH_TYPE_ROW, PDO::FETCH_CLASS, __CLASS__);
	}

	/**
	 * Retourne un object utilisateur (si les information de connection sont valide)
	 * @param string $_login nom d'utilisateur
	 * @param string $_mdp motsz de passe en sha512
	 * @return user object user
	 */
	public static function connect(string $_login, string $_mdp) {
		$sMdp = (!is_sha512($_mdp)) ? sha512($_mdp) : $_mdp;
		if (config::byKey('ldap:enable') == '1' && function_exists('ldap_connect')) {
			log::add("connection", "info", __('LDAP Authentification', __FILE__));
			$ad = ldap_connect(config::byKey('ldap:host'), config::byKey('ldap:port'));
			if (!$ad) {
				log::add("connection", "info", __('Connection LDAP Error', __FILE__));
				return false;
			}
			log::add("connection", "info", __('LDAP Connection OK', __FILE__));
			ldap_set_option($ad, LDAP_OPT_PROTOCOL_VERSION, 3);
			ldap_set_option($ad, LDAP_OPT_REFERRALS, 0);
			if (config::byKey('ldap:tls')) {
				if (!ldap_start_tls($ad)) {
					log::add("connection", "debug", __('start TLS KO', __FILE__));
					return false;
				} else {
					log::add("connection", "debug", __('start TLS OK', __FILE__));
				}
			}
			if (config::byKey('ldap:samba4')) {
				if (!ldap_bind($ad, $_login . '@' . config::byKey('ldap:domain'), $_mdp)) {
					log::add("connection", "info", __('LDAP bind user - login/password denied', __FILE__));
					return false;
				}
			} else {
				if (!ldap_bind($ad, config::byKey('ldap::usersearch') . '=' . $_login . ',' . config::byKey('ldap:basedn'), $_mdp)) {
					log::add("connection", "info", __('LDAP bind user - login/password denied', __FILE__));
					return false;
				}
			}
			log::add("connection", "debug", __('LDAP Bind user - OK', __FILE__));
			if (config::bykey('ldap:filter:admin') == "" && config::bykey('ldap:filter:user') == "" && config::bykey('ldap:filter:restrict') == "") {
				log::add("connection", "warning", __('LDAP Profile Check - [WARNING] None filter was set, "', __FILE__) . $_login . __('"  authenticated as an administrator', __FILE__));
				$profile = 'admin';
			} else {
				foreach (array('admin', 'user', 'restrict', 'none') as $profile) {
					if ($profile == 'none') {
						log::add("connection", "warning", __('LDAP Profile Check - [WARNING] No profile has been set for "', __FILE__) . $_login . __('" user in the LDAP', __FILE__));
						break;
					}
					if (config::bykey('ldap:filter:' . $profile) != "") {
						$filters = '(&(' . config::bykey('ldap::usersearch') . '=' . $_login . ')' . config::bykey('ldap:filter:' . $profile) . ')';
						log::add("connection", "debug", __('LDAP Profile Check - filter:, "' . $filters . '"', __FILE__));
						$result = ldap_search($ad, config::byKey('ldap:basedn'), $filters);
						$entries = ldap_get_entries($ad, $result);
						if ($entries['count'] > 0) {
							log::add("connection", "info", __('LDAP Profile Check - The "', __FILE__) . $profile . __('" profile was FOUND for "', __FILE__) . $_login . __('" user in the LDAP', __FILE__));
							break;
						}
					}
				}
			}
			if ($profile != 'none') {
				$user = self::byLogin($_login);
				if (is_object($user)) {
					$user->setPassword($sMdp)
						->setOptions('lastConnection', date('Y-m-d H:i:s'))
						->setProfils($profile);
					$user->save();
					return $user;
				}
				$user = (new user)
					->setLogin($_login)
					->setPassword($sMdp)
					->setOptions('lastConnection', date('Y-m-d H:i:s'))
					->setProfils($profile);
				$user->save();
				log::add("connection", "info", __('User created from the LDAP :', __FILE__) . ' ' . $_login);
				jeedom::event('user_connect');
				// TODO : if username == password => change ldap password
				log::add('event', 'info', __('User connection accepted', __FILE__) . ' ' . $_login);
				return $user;
			} else {
				$user = self::byLogin($_login);
				if (is_object($user)) {
					$user->remove();
				}
				log::add("connection", "info", __('User not allowed to access to Jeedom according to the LDAP (', __FILE__) . $_login . ')');
			}
		}
		$user = user::byLoginAndPassword($_login, $sMdp);
		if (!is_object($user)) {
			$user = user::byLoginAndPassword($_login, sha1($_mdp));
			if (is_object($user)) {
				$user->setPassword($sMdp);
				log::add('event', 'info', __('Local account found for', __FILE__) . ' ' . $_login);
			}
		}
		if (is_object($user)) {
			$user->setOptions('lastConnection', date('Y-m-d H:i:s'));
			$user->save();
			jeedom::event('user_connect');
			log::add('event', 'info', __('Local account found for', __FILE__) . ' ' . $_login);
			log::add('event', 'info', __('User connection accepted', __FILE__) . ' ' . $_login);
		}
		return $user;
	}

	public static function connectToLDAP() {
		$ad = ldap_connect(config::byKey('ldap:host'), config::byKey('ldap:port'));
		ldap_set_option($ad, LDAP_OPT_PROTOCOL_VERSION, 3);
		ldap_set_option($ad, LDAP_OPT_REFERRALS, 0);
		if (config::byKey('ldap:tls') && !ldap_start_tls($ad)) {
			return false;
		}
		if (config::byKey('ldap:samba4')) {
			if (ldap_bind($ad, config::byKey('ldap:username') . "@" . config::byKey('ldap:domain'), config::byKey('ldap:password'))) {
				return $ad;
			}
		} else {
			if (ldap_bind($ad, config::byKey('ldap:username'), config::byKey('ldap:password'))) {
				return $ad;
			}
		}
		return false;
	}

	public static function byLogin($_login) {
		$values = array(
			'login' => $_login,
		);
		$sql = 'SELECT ' . DB::buildField(__CLASS__) . '
		FROM user
		WHERE login=:login';
		return DB::Prepare($sql, $values, DB::FETCH_TYPE_ROW, PDO::FETCH_CLASS, __CLASS__);
	}

	public static function byHash($_hash) {
		$values = array(
			'hash' => $_hash,
		);
		$sql = 'SELECT ' . DB::buildField(__CLASS__) . '
		FROM user';
		if (is_sha512($_hash)) {
			$sql .= ' WHERE SHA2(hash,512)=:hash';
		} else {
			$sql .= ' WHERE hash=:hash';
		}
		return DB::Prepare($sql, $values, DB::FETCH_TYPE_ROW, PDO::FETCH_CLASS, __CLASS__);
	}

	public static function byLoginAndHash($_login, $_hash) {
		$values = array(
			'login' => $_login,
			'hash' => $_hash,
		);
		$sql = 'SELECT ' . DB::buildField(__CLASS__) . '
		FROM user
		WHERE login=:login
		AND hash=:hash';
		return DB::Prepare($sql, $values, DB::FETCH_TYPE_ROW, PDO::FETCH_CLASS, __CLASS__);
	}

	public static function byLoginAndPassword(string $_login, string $_password) {
		$values = array(
			'login' => $_login,
			'password' => $_password,
		);
		$sql = 'SELECT ' . DB::buildField(__CLASS__) . '
		FROM user
		WHERE login=:login
		AND password=:password';
		return DB::Prepare($sql, $values, DB::FETCH_TYPE_ROW, PDO::FETCH_CLASS, __CLASS__);
	}

	/**
	 *
	 * @return array de tous les utilisateurs
	 */
	public static function all() {
		$sql = 'SELECT ' . DB::buildField(__CLASS__) . '
		FROM user ORDER by login';
		return DB::Prepare($sql, array(), DB::FETCH_TYPE_ALL, PDO::FETCH_CLASS, __CLASS__);
	}

	public static function searchByRight(string $_rights) {
		$values = array(
			'rights' => '%"' . $_rights . '":1%',
			'rights2' => '%"' . $_rights . '":"1"%',
		);
		$sql = 'SELECT ' . DB::buildField(__CLASS__) . '
		FROM user
		WHERE rights LIKE :rights
		OR rights LIKE :rights2';
		return DB::Prepare($sql, $values, DB::FETCH_TYPE_ALL, PDO::FETCH_CLASS, __CLASS__);
	}

	public static function searchByOptions($_search) {
		$value = array(
			'search' => '%' . $_search . '%'
		);
		$sql = 'SELECT ' . DB::buildField(__CLASS__) . '
		FROM user
		WHERE options LIKE :search';
		return DB::Prepare($sql, $value, DB::FETCH_TYPE_ALL, PDO::FETCH_CLASS, __CLASS__);
	}

	public static function byProfils($_profils, $_enable = false) {
		$values = array(
			'profils' => $_profils,
		);
		$sql = 'SELECT ' . DB::buildField(__CLASS__) . '
		FROM user
		WHERE profils=:profils';
		if ($_enable) {
			$sql .= ' AND enable=1';
		}
		return DB::Prepare($sql, $values, DB::FETCH_TYPE_ALL, PDO::FETCH_CLASS, __CLASS__);
	}

	public static function byEnable($_enable) {
		$values = array(
			'enable' => $_enable,
		);
		$sql = 'SELECT ' . DB::buildField(__CLASS__) . '
		FROM user
		WHERE enable=:enable';
		return DB::Prepare($sql, $values, DB::FETCH_TYPE_ALL, PDO::FETCH_CLASS, __CLASS__);
	}

	public static function failedLogin(): void {
		$current_ip = getClientIp();
		$failed_login = cache::byKey('security::failed_login::'.$current_ip);
		cache::set('security::failed_login::'.$current_ip,($failed_login->getValue(0)+1), config::byKey('security::timeLoginFailed'));
		if(($failed_login->getValue(0)+1) > config::byKey('security::maxFailedLogin')){
		    $ban_ips = json_decode(cache::byKey('security::banip')->getValue('[]'), true);
			$ban_ips[$current_ip] = strtotime('now');
			cache::set('security::banip', json_encode($ban_ips));
		}
	}

	public static function removeBanIp(): void {
		$cache = cache::byKey('security::banip');
		$cache->remove();
	}

	public static function isBan(): bool {
		$current_ip = getClientIp();
		if ($current_ip == '') {
			return false;
		}
		$whiteIps = explode(';', config::byKey('security::whiteips'));
		if (is_array($whiteIps) && count($whiteIps) > 0) {
			foreach ($whiteIps as $whiteip) {
				if (netMatch($whiteip, $current_ip)) {
					return false;
				}
			}
		}
		$ban_ips = json_decode(cache::byKey('security::banip')->getValue('[]'), true);
		if (!is_array($ban_ips) || count($ban_ips) == 0) {
			return false;
		}
		if (count($ban_ips) > 0 && is_int(intval(config::byKey('security::bantime')))) {
			foreach ($ban_ips as $ip => $datetime) {
				if(!is_int(intval($datetime))){
					continue;
				}
				if (config::byKey('security::bantime') == -1 || intval($datetime) + intval(config::byKey('security::bantime')) > strtotime('now')) {
					if ($ip == $current_ip) {
						jeedom::event('ip_ban', false, ['ip' => $ip, 'datetime' => intval($datetime)]);
                      	return true;
					}
					continue;
				}
				unset($ban_ips[$ip]);
			}
			cache::set('security::banip', json_encode($ban_ips));
		}
		return false;
	}
	public static function getAccessKeyForReport(): string {
		$user = user::byLogin('internal_report');
		if (!is_object($user)) {
			$user = new user();
			$user->setLogin('internal_report');
			$google2fa = new Google2FA();
			$user->setOptions('twoFactorAuthentificationSecret', $google2fa->generateSecretKey());
			$user->setOptions('twoFactorAuthentification', 1);
		}
		$user->setPassword(sha512(config::genKey(255)));
		$user->setOptions('localOnly', 1);
		$user->setProfils('admin');
		$user->setEnable(1);
		$key = config::genKey();
		$registerDevice = array(
			sha512($key) => array(
				'datetime' => date('Y-m-d H:i:s'),
				'ip' => '127.0.0.1',
				'session_id' => 'none',
			),
		);
		$user->setOptions('registerDevice', $registerDevice);
		$user->save();
		try {
			$sessions = listSession();
			foreach ($sessions as $id => $session) {
				if ($session['user_id'] == $user->getId()) {
					deleteSession($id);
				}
			}
		} catch (\Exception $e) {
		}
		return $user->getHash() . '-' . $key;
	}

	public static function supportAccess($_enable = true) {
		if ($_enable) {
			$user = user::byLogin('jeedom_support');
			if (!is_object($user)) {
				$user = new user();
				$user->setLogin('jeedom_support');
			}
			$user->setPassword(sha512(config::genKey(255)));
			$user->setProfils('admin');
			$user->setEnable(1);
			$key = config::genKey();
			$registerDevice = array(
				sha512($key) => array(
					'datetime' => date('Y-m-d H:i:s'),
					'ip' => '127.0.0.1',
					'session_id' => 'none',
				),
			);
			$user->setOptions('registerDevice', $registerDevice);
			$user->save();
			repo_market::supportAccess(true, $user->getHash() . '-' . $key);
		} else {
			$user = user::byLogin('jeedom_support');
			if (is_object($user)) {
				$user->remove();
			}
			repo_market::supportAccess(false);
		}
	}

	public static function deadCmd() {
		$return = array();
		foreach ((user::all()) as $user) {
			$cmd = $user->getOptions('notification::cmd');
			if ($cmd != '') {
				if (!cmd::byId(str_replace('#', '', $cmd))) {
					$return[] = array('detail' => __('Utilisateur', __FILE__), 'help' => __('Commande notification utilisateur', __FILE__), 'who' => $cmd);
				}
			}
		}
		return $return;
	}

	public static function regenerateHash() {
		foreach ((user::all()) as $user) {
			if ($user->getProfils() != 'admin' || $user->getOptions('doNotRotateHash', 0) == 1 || $user->getEnable() == 0) {
				continue;
			}
			if (strtotime($user->getOptions('hashGenerated')) > strtotime('now -3 month')) {
				continue;
			}
			$user->setHash('');
			$user->getHash();
		}
	}

	/*     * *********************Méthodes d'instance************************* */

	public function preInsert(): void {
		if (is_object(self::byLogin($this->getLogin()))) {
			throw new Exception(__('Ce nom d\'utilisateur est déja pris', __FILE__));
		}
	}

	public function preSave(): void {
		if ($this->getLogin() == '') {
			throw new Exception(__('Le nom d\'utilisateur ne peut pas être vide', __FILE__));
		}
		if ($this->getPassword() == '') {
			throw new Exception(__('Le mot de passe ne peut etre vide', __FILE__));
		}
		$admins = user::byProfils('admin', true);
		if (count($admins) == 1 && $admins[0]->getId() == $this->getId()) {
			if ($this->getProfils() == 'admin' && $this->getEnable() == 0) {
				throw new Exception(__('Vous ne pouvez désactiver le dernier utilisateur', __FILE__));
			}
			if ($this->getProfils() != 'admin') {
				throw new Exception(__('Vous ne pouvez changer le profil du dernier administrateur', __FILE__));
			}
		}
	}

	public function encrypt(): void {
		$this->getOptions('twoFactorAuthentification', utils::encrypt($this->getOptions('twoFactorAuthentification')));
	}

	public function decrypt(): void {
		$this->getOptions('twoFactorAuthentification', utils::decrypt($this->getOptions('twoFactorAuthentification')));
	}

	public function save() {
		return DB::save($this);
	}

	public function preRemove(): void {
		if (count(user::byProfils('admin', true)) == 1 && ($this->getProfils() == 'admin' && $this->getEnable() == 1)) {
			throw new Exception(__('Vous ne pouvez supprimer le dernier administrateur', __FILE__));
		}
	}

	public function remove() {
		jeedom::addRemoveHistory(array('id' => $this->getId(), 'name' => $this->getLogin(), 'date' => date('Y-m-d H:i:s'), 'type' => 'user'));
		return DB::remove($this);
	}

	public function refresh(): void {
		DB::refresh($this);
	}

	/**
	 *
	 * @return boolean vrai si l'utilisateur est valide
	 */
	public function is_Connected(): bool {
		return (is_numeric($this->id) && $this->login != '');
	}

	public function validateTwoFactorCode($_code) {
		$google2fa = new Google2FA();
		return $google2fa->verifyKey($this->getOptions('twoFactorAuthentificationSecret'), $_code, 8);
	}

	/*     * **********************Getteur Setteur*************************** */

	public function getId() {
		return $this->id;
	}

	public function getLogin() {
		return $this->login;
	}

	public function getPassword() {
		return $this->password;
	}

	public function setId($_id): self {
		$this->_changed = utils::attrChanged($this->_changed, $this->id, $_id);
		$this->id = $_id;
		return $this;
	}

	public function setLogin($_login): self {
		$this->_changed = utils::attrChanged($this->_changed, $this->login, $_login);
		$this->login = $_login;
		return $this;
	}

	public function setPassword(string $_password): self {
		if ($_password != '') {
			$_password = (!is_sha512($_password)) ? sha512($_password) : $_password;
		} else {
			throw new Exception(__('Le mot de passe ne peut etre vide', __FILE__));
		}
		$this->_changed = utils::attrChanged($this->_changed, $this->password, $_password);
		$this->password = $_password;
		return $this;
	}

	public function getOptions($_key = '', $_default = '') {
		return utils::getJsonAttr($this->options, $_key, $_default);
	}

	public function setOptions($_key, $_value) {
		if ($_key == 'registerDevice' && is_array($_value) &&  count($_value) > 20) {
			uasort($_value, function ($a, $b) {
				return strtotime($b['datetime']) - strtotime($a['datetime']);
			});
			$_value = array_slice($_value, 0, 20, true);
		}
		$options = utils::setJsonAttr($this->options, $_key, $_value);
		$this->_changed = utils::attrChanged($this->_changed, $this->options, $options);
		$this->options = $options;
		return $this;
	}

	public function getRights($_key = '', $_default = '') {
		return utils::getJsonAttr($this->rights, $_key, $_default);
	}

	public function setRights($_key, $_value): self {
		$rights = utils::setJsonAttr($this->rights, $_key, $_value);
		$this->_changed = utils::attrChanged($this->_changed, $this->rights, $rights);
		$this->rights = $rights;
		return $this;
	}

	public function getEnable() {
		return $this->enable;
	}

	public function setEnable($_enable): self {
		$this->_changed = utils::attrChanged($this->_changed, $this->enable, $_enable);
		$this->enable = $_enable;
		return $this;
	}

	public function getHash() {
		if ($this->hash == '' && $this->id != '') {
			$hash = config::genKey();
			while (is_object(self::byHash($hash))) {
				$hash = config::genKey();
			}
			$this->setHash($hash);
			$this->setOptions('hashGenerated', date('Y-m-d H:i:s'));
			$this->save();
		}
		return $this->hash;
	}

	public function setHash($_hash) {
		$this->_changed = utils::attrChanged($this->_changed, $this->hash, $_hash);
		$this->hash = $_hash;
		return $this;
	}

	public function getProfils() {
		return $this->profils;
	}

	public function setProfils($_profils): self {
		$this->_changed = utils::attrChanged($this->_changed, $this->profils, $_profils);
		$this->profils = $_profils;
		return $this;
	}

	public function getChanged() {
		return $this->_changed;
	}

	public function setChanged($_changed): self {
		$this->_changed = $_changed;
		return $this;
	}
}
