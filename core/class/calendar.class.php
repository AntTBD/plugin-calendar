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
require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';

class calendar extends eqLogic {
	/*     * *************************Attributs****************************** */

	public static $_widgetPossibility = array('custom' => true, 'custom::graph' => false, 'custom::layout' => false);

	/*     * ***********************Methode static*************************** */

	public static function pull($_option) {
		$event = calendar_event::byId($_option['event_id']);
		if (!is_object($event)) {
			return;
		}
		$eqLogic = $event->getEqLogic();
		if ($eqLogic->getIsEnable() == 0) {
			return;
		}
		$nowtime = strtotime('now');
		$repeat = $event->getRepeat();
		if ($repeat['enable'] == 1) {
			if ($repeat['nationalDay'] == 'onlyNationalDay' || !isset($repeat['freq']) || $repeat['freq'] == '' || $repeat['unite'] == '') {
				$startDate = (new DateTime('-12 month ' . date('Y-m-d H:i:s')))->format('Y-m-d H:i:s');
				$endDate = (new DateTime('+12 month ' . date('Y-m-d H:i:s')))->format('Y-m-d H:i:s');
			} else {
				if($repeat['unite'] == 'hours'){
					$startDate = (new DateTime('-' . 8 * $repeat['freq'] . ' ' . $repeat['unite'] . ' ' . date('Y-m-d H:i:s')))->format('Y-m-d H:i:s');
					$endDate = (new DateTime('+' . 9999 * $repeat['freq'] . ' ' . $repeat['unite'] . ' ' . date('Y-m-d H:i:s')))->format('Y-m-d H:i:s');
				}else{
					$startDate = (new DateTime('-' . 8 * $repeat['freq'] . ' ' . $repeat['unite'] . ' ' . date('Y-m-d H:i:s')))->format('Y-m-d H:i:s');
					$endDate = (new DateTime('+' . 99 * $repeat['freq'] . ' ' . $repeat['unite'] . ' ' . date('Y-m-d H:i:s')))->format('Y-m-d H:i:s');
				}
			}
		} else {
			$startDate = null;
			$endDate = null;
		}
		log::add(__CLASS__, 'debug', $eqLogic->getHumanName() . ' ' . __('Reprogrammation', __FILE__));
		$event->reschedule();
		log::add(__CLASS__, 'debug', $eqLogic->getHumanName() . ' ' . __('Analyse de l\'évènement', __FILE__) . ' : ' . print_r($event, true));
		try {
			if (jeedom::isDateOk()) {
				$results = $event->calculOccurrence($startDate, $endDate);
				if (count($results) == 0) {
					log::add(__CLASS__, 'debug', $eqLogic->getHumanName() . ' ' . __('Aucune programmation trouvée, exécution des actions de fin', __FILE__));
					$event->doAction('end');
					return null;
				}
				log::add(__CLASS__, 'debug', $eqLogic->getHumanName() . ' ' . __('Recherche de l\'action à exécuter (début ou fin)', __FILE__));
				for ($i = 0; $i < count($results); $i++) {
					if ((strtotime($results[$i]['end']) + 300) <= $nowtime) {
						continue;
					}
					if (strtotime($results[$i]['start']) <= $nowtime && strtotime($results[$i]['end']) > $nowtime) {
						log::add(__CLASS__, 'debug', $eqLogic->getHumanName() . ' ' . __('Action de début', __FILE__));
						$event->doAction('start');
						break;
					}
					if (strtotime($results[$i]['end']) <= $nowtime && (!isset($results[$i + 1]) || strtotime($results[$i + 1]['start']) > $nowtime)) {
						log::add(__CLASS__, 'debug', $eqLogic->getHumanName() . ' ' . __('Action de fin', __FILE__));
						$event->doAction('end');
						break;
					}
				}
			}
		} catch (Exception $e) {
		}
	}

	public static function start() {
		foreach (self::byType(__CLASS__) as $eqLogic) {
			$eqLogic->rescheduleEvent();
		}
	}

	public static function restore() {
		foreach (self::byType(__CLASS__) as $eqLogic) {
			$eqLogic->rescheduleEvent();
		}
	}

	public static function cronDaily() {
		foreach (self::byType(__CLASS__) as $eqLogic) {
			$eqLogic->rescheduleEvent();
		}
	}

	public static function orderEvent($a, $b) {
		$al = strtolower($a['start']);
		$bl = strtolower($b['start']);
		if ($al == $bl) {
			return 0;
		}
		return ($al > $bl) ? +1 : -1;
	}

	public static function deadCmd() {
		$return = array();
		foreach (eqLogic::byType(__CLASS__) as $calendar) {
			foreach (calendar_event::getEventsByEqLogic($calendar->getId()) as $events) {
				foreach ($events->getCmd_param()['start'] as $cmdStart) {
					if ($cmdStart['cmd'] != '' && strpos($cmdStart['cmd'], '#') !== false) {
						if (!cmd::byId(str_replace('#', '', $cmdStart['cmd']))) {
							$return[] = array('detail' => __('Calendrier', __FILE__) . ' ' . $calendar->getHumanName() . ' ' . __('dans l\'évènement', __FILE__) . ' ' . $events->getCmd_param()['eventName'], 'help' => __('Action de début', __FILE__), 'who' => $cmdStart['cmd']);
						}
					}
				}
				foreach ($events->getCmd_param()['end'] as $cmdEnd) {
					if ($cmdEnd['cmd'] != '' && strpos($cmdEnd['cmd'], '#') !== false) {
						if (!cmd::byId(str_replace('#', '', $cmdEnd['cmd']))) {
							$return[] = array('detail' => __('Calendrier', __FILE__) . ' ' . $calendar->getHumanName() . ' ' . __('dans l\'évènement', __FILE__) . ' ' . $events->getCmd_param()['eventName'], 'help' => __('Action de fin', __FILE__), 'who' => $cmdEnd['cmd']);
						}
					}
				}
			}
		}
		return $return;
	}

	public static function customUsedBy($_type, $_id) {
		if ($_type == 'cmd') {
			return calendar_event::searchByCmd_param('#' . $_id . '#');
		}
		if ($_type == 'eqLogic') {
			return array_merge(calendar_event::searchByCmd_param('#eqLogic' . $_id . '#'), calendar_event::searchByCmd_param('"eqLogic":"' . $_id . '"'));
		}
		if ($_type == 'scenario') {
			return array_merge(calendar_event::searchByCmd_param('#scenario' . $_id . '#'), calendar_event::searchByCmd_param('"scenario_id":"' . $_id . '"'));
		}
	}



	/*     * *********************Methode d'instance************************* */

	public function copy($_name) {
		$eqLogicCopy = clone $this;
		$eqLogicCopy->setName($_name);
		$eqLogicCopy->setId('');
		$eqLogicCopy->save();
		foreach ($this->getEvents() as $event) {
			$eventCopy = clone $event;
			$eventCopy->setId('');
			$eventCopy->setEqLogic_id($eqLogicCopy->getId());
			$eventCopy->save();
		}
		return $eqLogicCopy;
	}

	public function preRemove() {
		foreach ($this->getEvents() as $event) {
			$event->remove();
		}
	}

	public function preSave() {
		if ($this->getConfiguration('nbWidgetDay') == '') {
			$this->setConfiguration('nbWidgetDay', 7);
		}
	}

	public function preInsert() {
		$this->setIsEnable(1);
	}

	public function postSave() {
		$state = $this->getCmd(null, 'state');
		if (is_object($state)) {
			$state->remove();
		}

		$enable = $this->getCmd(null, 'enable');
		if (is_object($enable)) {
			$enable->remove();
		}

		$disable = $this->getCmd(null, 'disable');
		if (is_object($disable)) {
			$disable->remove();
		}

		$cmd = $this->getCmd(null, 'in_progress');
		if (!is_object($cmd)) {
			$cmd = new calendarCmd();
			$cmd->setIsVisible(0);
		}
		$cmd->setEqLogic_id($this->getId());
		$cmd->setName(__('En cours', __FILE__));
		$cmd->setType('info');
		$cmd->setSubType('string');
		$cmd->setLogicalId('in_progress');
		$cmd->save();

		$events_name = array();
		$events = $this->getEvents();
		if (count($events) > 0) {
			foreach ($events as $event) {
				$events_name[] = $event->getName();
			}
		}

		$cmd = $this->getCmd(null, 'add_include_date');
		if (!is_object($cmd)) {
			$cmd = new calendarCmd();
			$cmd->setIsVisible(0);
		}
		$cmd->setEqLogic_id($this->getId());
		$cmd->setName(__('Ajouter une date', __FILE__));
		$cmd->setType('action');
		$cmd->setSubType('message');
		$cmd->setLogicalId('add_include_date');
		$cmd->setDisplay('message_placeholder', __('Date (AAAA-MM-JJ)', __FILE__));
		$cmd->setDisplay('title_placeholder', __('Nom évènement', __FILE__));
		$cmd->setDisplay('title_possibility_list', json_encode($events_name));
		$cmd->save();

		$cmd = $this->getCmd(null, 'add_exclude_date');
		if (!is_object($cmd)) {
			$cmd = new calendarCmd();
			$cmd->setIsVisible(0);
		}
		$cmd->setEqLogic_id($this->getId());
		$cmd->setName(__('Retirer une date', __FILE__));
		$cmd->setType('action');
		$cmd->setSubType('message');
		$cmd->setLogicalId('add_exclude_date');
		$cmd->setDisplay('message_placeholder', __('Date (AAAA-MM-JJ)', __FILE__));
		$cmd->setDisplay('title_placeholder', __('Nom évènement', __FILE__));
		$cmd->setDisplay('title_possibility_list', json_encode($events_name));
		$cmd->save();

		$this->rescheduleEvent();
		$this->refreshWidget();
	}

	public function rescheduleEvent() {
		log::add(__CLASS__, 'debug', $this->getHumanName() . ' ' . __('Reprogrammation de tous les évènements', __FILE__));
		foreach ($this->getEvents() as $event) {
			$event->save();
		}
	}

	public function toHtml($_version = 'dashboard') {
		$replace = $this->preToHtml($_version);
		if (!is_array($replace)) {
			return $replace;
		}
		$version = jeedom::versionAlias($_version);

		$startDate = (new DateTime('-' . $this->getConfiguration('nbWidgetDay', 7) . ' days ' . date('Y-m-d H:i:s')))->format('Y-m-d H:i:s');
		$endDate = (new DateTime('+' . $this->getConfiguration('nbWidgetDay', 7) . ' days ' . date('Y-m-d H:i:s')))->format('Y-m-d H:i:s');
		$events = calendar_event::calculeEvents(calendar_event::getEventsByEqLogic($this->getId(), $startDate, $endDate), $startDate, $endDate);
		usort($events, 'calendar::orderEvent');
		$tEvent = getTemplate('core', $version, 'event', __CLASS__);
		$dEvent = '';
		$nbEvent = 1;
		$eventList = array();
		foreach ($events as $event) {
			if ($this->getConfiguration('nbWidgetMaxEvent', 0) != 0 && $this->getConfiguration('nbWidgetMaxEvent', 0) < $nbEvent) {
				break;
			}
			if (strtotime($event['end']) < strtotime('now') || strtotime($event['start']) > strtotime($endDate)) {
				continue;
			}
			if (isset($eventList[$this->getId() . '_' . $event['id'] . '_' . $event['start'] . '_' . $event['end']])) {
				continue;
			}
			$eventList[$this->getId() . '_' . $event['id'] . '_' . $event['start'] . '_' . $event['end']] = true;
			if ($event['noDisplayOnDashboard'] == 0) {
				$replaceCmd = array(
					'#uid#' => mt_rand() . $this->getId() . $event['id'],
					'#event_id#' => $event['id'],
					'#name#' => $event['title'],
					'#date#' => $event['start'],
					'#start#' => date_fr(date('D', strtotime($event['start']))) . ' ' . date('d', strtotime($event['start'])) . ' ' . date_fr(date('M', strtotime($event['start']))) . ' ' . date('H:i', strtotime($event['start'])),
					'#end#' => date_fr(date('D', strtotime($event['end']))) . ' ' . date('d', strtotime($event['end'])) . ' ' . date_fr(date('M', strtotime($event['end']))) . ' ' . date('H:i', strtotime($event['end'])),
					'#background_color#' => $event['color'],
					'#text_color#' => $event['textColor'],
				);
				$dEvent .= template_replace($replaceCmd, $tEvent);
				$nbEvent++;
			}
		}
		$replace['#events#'] = $dEvent;
		return template_replace($replace, getTemplate('core', $version, 'eqLogic', __CLASS__));
	}

	/*     * **********************Getteur Setteur*************************** */

	public function getEvents() {
		return calendar_event::getEventsByEqLogic($this->getId());
	}
}

class calendarCmd extends cmd {
	/*     * *************************Attributs****************************** */

	public static $_widgetPossibility = array('custom' => false);

	/*     * *********************Methode d'instance************************* */

	public function dontRemoveCmd() {
		if (in_array($this->getLogicalId(), array('in_progress', 'add_exclude_date', 'add_include_date'))) {
			return true;
		}
		return false;
	}

	public function postInsert() {
		if ($this->getLogicalId() == 'in_progress') {
			$this->event($this->execute());
		}
	}

	public function execute($_options = null) {
		$eqLogic = $this->getEqLogic();
		if ($this->getLogicalId() == 'in_progress') {
			$return = '';
			foreach ($eqLogic->getEvents() as $event) {
				if ($event->getCmd_param('in_progress', 0) == 1) {
					if ($event->getCmd_param('eventName') != '') {
						$return .= $event->getCmd_param('eventName') . ', ';
					} else {
						$return .= $event->getCmd_param('name') . ', ';
					}
				}
			}
			$return = trim(trim(trim($return), ','));
			if ($return == '') {
				$return = __('Aucun', __FILE__);
			}
			return $return;
		}
		if ($this->getLogicalId() == 'add_exclude_date') {
			$events = $eqLogic->getEvents();
			$toDoEvent = explode(',', $_options['title']);
			foreach ($events as $event) {
				if (!in_array($event->getCmd_param('eventName'), $toDoEvent)) {
					continue;
				}
				$event->setRepeat('includeDate', trim(str_replace($_options['message'], '', $event->getRepeat('includeDate')), ','));
				$event->setRepeat('excludeDate', trim(str_replace($_options['message'], '', $event->getRepeat('excludeDate')), ','));
				$event->setRepeat('excludeDate', trim($event->getRepeat('excludeDate') . ',' . $_options['message'], ','));
				$event->save();
				$eqLogic->refreshWidget();
			}
			return;
		}
		if ($this->getLogicalId() == 'add_include_date') {
			$events = $eqLogic->getEvents();
			$toDoEvent = explode(',', $_options['title']);
			foreach ($events as $event) {
				if (!in_array($event->getCmd_param('eventName'), $toDoEvent)) {
					continue;
				}
				$event->setRepeat('excludeDate', trim(str_replace($_options['message'], '', $event->getRepeat('excludeDate')), ','));
				$event->setRepeat('includeDate', trim(str_replace($_options['message'], '', $event->getRepeat('includeDate')), ','));
				$event->setRepeat('includeDate', trim($event->getRepeat('includeDate') . ',' . $_options['message'], ','));
				$event->save();
				$eqLogic->refreshWidget();
			}
			return;
		}
	}
}

class calendar_event {
	/*     * *************************Attributs****************************** */

	private $id;
	private $eqLogic_id;
	private $cmd_param;
	private $startDate;
	private $endDate;
	private $repeat;
	private $until = null;
	private $_changed = false;

	/*     * ***********************Methode static*************************** */

	public static function sortEventDate($a, $b) {
		if (strtotime($a['start']) == strtotime($b['start'])) {
			return 0;
		}
		return (strtotime($a['start']) < strtotime($b['start'])) ? -1 : 1;
	}

	public static function cleanEvents() {
		$events = self::all();
		foreach ($events as $event) {
			if (!is_object($event->getEqLogic())) {
				$event->remove();
				continue;
			}
			$repeat = $event->getRepeat();
			if ($repeat['includeDateFromCalendar'] != '' && $repeat['includeDateFromEvent'] == '') {
				$event->setRepeat('includeDateFromEvent', $repeat['includeDateFromCalendar']);
				$event->setRepeat('includeDateFromCalendar', self::byId($repeat['includeDateFromCalendar'])->getEqLogic_id());
				$event->save();
			}
			if ($repeat['excludeDateFromCalendar'] != '' && $repeat['excludeDateFromEvent'] == '') {
				$event->setRepeat('excludeDateFromEvent', $repeat['excludeDateFromCalendar']);
				$event->setRepeat('excludeDateFromCalendar', self::byId($repeat['excludeDateFromCalendar'])->getEqLogic_id());
				$event->save();
			}
		}
	}

	public static function byId($_id) {
		$values = array(
			'id' => $_id,
		);
		$sql = 'SELECT ' . DB::buildField(__CLASS__) . '
		FROM calendar_event
		WHERE id=:id';
		return DB::Prepare($sql, $values, DB::FETCH_TYPE_ROW, PDO::FETCH_CLASS, __CLASS__);
	}

	public static function searchByCmd($_cmd_id) {
		$values = array(
			'cmd_param' => '%"cmd":"#' . $_cmd_id . '#"%',
		);
		$sql = 'SELECT ' . DB::buildField(__CLASS__) . '
		FROM calendar_event
		WHERE cmd_param LIKE :cmd_param';
		return DB::Prepare($sql, $values, DB::FETCH_TYPE_ALL, PDO::FETCH_CLASS, __CLASS__);
	}

	public static function all() {
		$sql = 'SELECT ' . DB::buildField(__CLASS__) . '
		FROM calendar_event';
		return DB::Prepare($sql, array(), DB::FETCH_TYPE_ALL, PDO::FETCH_CLASS, __CLASS__);
	}

	public static function searchByCmd_param($_search) {
		$value = array(
			'search' => '%' . $_search . '%',
		);
		$sql = 'SELECT ' . DB::buildField(__CLASS__) . '
		FROM calendar_event
		WHERE cmd_param LIKE :search';
		return DB::Prepare($sql, $value, DB::FETCH_TYPE_ALL, PDO::FETCH_CLASS, __CLASS__);
	}

	public static function getEventsByEqLogic($_eqLogic_id, $_startDate = null, $_endDate = null) {
		$values = array(
			'eqLogic_id' => $_eqLogic_id,
		);
		$sql = 'SELECT ' . DB::buildField(__CLASS__) . '
		FROM calendar_event
		WHERE eqLogic_id=:eqLogic_id';
		if ($_startDate != null && $_endDate != null) {
			$values['startDate'] = $_startDate;
			$values['endDate'] = $_endDate;
			$sql .= ' AND ((startDate >=:startDate
				AND startDate <=:endDate)
				OR until >=:startDate
				OR until = "0000-00-00 00:00:00"
				OR until is NULL)';
		}
		$sql .= ' ORDER BY startDate';
		return DB::Prepare($sql, $values, DB::FETCH_TYPE_ALL, PDO::FETCH_CLASS, __CLASS__);
	}

	public static function calculeEvents($_events, $_startDate = null, $_endDate = null) {
		$return = array();
		foreach ($_events as $event) {
			foreach ($event->calculOccurrence($_startDate, $_endDate) as $info_event) {
				$info_event['id'] = $event->getId();
				if ($event->getCmd_param('transparent', 0) == 1) {
					$info_event['color'] = 'transparent';
				} else {
					$info_event['color'] = $event->getCmd_param('color', '#2980b9');
				}
				$info_event['textColor'] = $event->getCmd_param('text_color', 'white');
				$info_event['noDisplayOnDashboard'] = $event->getCmd_param('noDisplayOnDashboard');
				if ($event->getCmd_param('eventName') != '') {
					$info_event['title'] = $event->getCmd_param('icon') . ' ' . $event->getCmd_param('eventName');
				} else {
					$info_event['title'] = $event->getCmd_param('icon') . ' ' . $event->getCmd_param('name');
				}
				$return[] = jeedom::toHumanReadable($info_event);
			}
		}
		return $return;
	}

	public static function getNationalDay($year = null) {
		if ($year === null) {
			$year = intval(date('Y'));
		}
		$easterDate = easter_date($year);
		$easterDay = date('j', $easterDate);
		$easterMonth = date('n', $easterDate);
		$easterYear = date('Y', $easterDate);
		$holidays = array(
			// Dates fixes
			date('Y-m-d', mktime(0, 0, 0, 1, 1, $year)), // 1er janvier
			date('Y-m-d', mktime(0, 0, 0, 5, 1, $year)), // Fête du travail
			date('Y-m-d', mktime(0, 0, 0, 5, 8, $year)), // Victoire des alliés
			date('Y-m-d', mktime(0, 0, 0, 7, 14, $year)), // Fête nationale
			date('Y-m-d', mktime(0, 0, 0, 8, 15, $year)), // Assomption
			date('Y-m-d', mktime(0, 0, 0, 11, 1, $year)), // Toussaint
			date('Y-m-d', mktime(0, 0, 0, 11, 11, $year)), // Armistice
			date('Y-m-d', mktime(0, 0, 0, 12, 25, $year)), // Noel
			// Dates variables
			date('Y-m-d', mktime(0, 0, 0, $easterMonth, $easterDay + 1, $easterYear)),
			date('Y-m-d', mktime(0, 0, 0, $easterMonth, $easterDay + 39, $easterYear)),
			date('Y-m-d', mktime(0, 0, 0, $easterMonth, $easterDay + 50, $easterYear)),

			date('Y-m-d', mktime(0, 0, 0, 1, 1, $year + 1)), // 1er janvier
			date('Y-m-d', mktime(0, 0, 0, 5, 1, $year + 1)), // Fête du travail
			date('Y-m-d', mktime(0, 0, 0, 5, 8, $year + 1)), // Victoire des alliés
			date('Y-m-d', mktime(0, 0, 0, 7, 14, $year + 1)), // Fête nationale
			date('Y-m-d', mktime(0, 0, 0, 8, 15, $year + 1)), // Assomption
			date('Y-m-d', mktime(0, 0, 0, 11, 1, $year + 1)), // Toussaint
			date('Y-m-d', mktime(0, 0, 0, 11, 11, $year + 1)), // Armistice
			date('Y-m-d', mktime(0, 0, 0, 12, 25, $year + 1)), // Noel
		);
		sort($holidays);
		return $holidays;
	}

	/*     * *********************Methode d'instance************************* */

	public function getLinkData(&$_data = array('node' => array(), 'link' => array()), $_level = 0, $_drill = 3) {
		if (isset($_data['node']['calendar' . $this->getId()])) {
			return;
		}
		$_level++;
		if ($_level > $_drill) {
			return $_data;
		}
		$_data['node']['calendar' . $this->getId()] = array(
			'id' => 'calendar' . $this->getId(),
			'type' => __('Agenda', __FILE__),
			'name' => __('Agenda', __FILE__),
			'image' => 'plugins/calendar/plugin_info/calendar_icon.png',
			'fontsize' => '1.5em',
			'fontweight' => ($_level == 1) ? 'bold' : 'normal',
			'width' => 40,
			'height' => 40,
			'texty' => -14,
			'textx' => 0,
			'title' => $this->getName(),
			'url' => 'index.php?v=d&p=calendar&m=calendar',
		);
	}

	public function reschedule() {
		$next = $this->nextOccurrence();
		if ($next === null || $next === false) {
			log::add('calendar', 'debug', $this->getEqLogic()->getHumanName() . ' ' . __('Aucune reprogrammation car aucune occurrence suivante trouvée', __FILE__));
			return;
		}
		log::add('calendar', 'debug', $this->getEqLogic()->getHumanName() . ' ' . __('Reprogrammation à', __FILE__) . ' : ' . print_r($next, true) . ' ' . __('de', __FILE__) . ' : ' . print_r($this, true));
		$cron = cron::byClassAndFunction('calendar', 'pull', array('event_id' => intval($this->getId())));
		if ($next != null) {
			if (!is_object($cron)) {
				$cron = new cron();
				$cron->setClass('calendar');
				$cron->setFunction('pull');
				$cron->setOption(array('event_id' => intval($this->getId())));
				$cron->setLastRun(date('Y-m-d H:i:s'));
			}
			$next = strtotime($next);
			$cron->setSchedule(date('i', $next) . ' ' . date('H', $next) . ' ' . date('d', $next) . ' ' . date('m', $next) . ' * ' . date('Y', $next));
			$cron->save();
		} else {
			if (is_object($cron)) {
				$cron->remove(false);
			}
		}
	}

	public function nextOccurrence($_position = null, $_details = false) {
		$startDate = null;
		$endDate = null;
		$repeat = $this->getRepeat();
		if ($repeat['enable'] == 1) {
			if ($repeat['nationalDay'] == 'onlyNationalDay' || !isset($repeat['freq']) || $repeat['freq'] == '' || $repeat['unite'] == '') {
				$startDate = (new DateTime('-12 month ' . date('Y-m-d H:i:s')))->format('Y-m-d H:i:s');
				$endDate = (new DateTime('+12 month ' . date('Y-m-d H:i:s')))->format('Y-m-d H:i:s');
			} else {
				$startDate = (new DateTime('-' . (8 * $repeat['freq']) . ' ' . $repeat['unite'] . ' ' . date('Y-m-d H:i:s')))->format('Y-m-d H:i:s');
				$endDate = (new DateTime('+' . (99 * $repeat['freq']) . ' ' . $repeat['unite'] . ' ' . date('Y-m-d H:i:s')))->format('Y-m-d H:i:s');
			}
		}
		$results = $this->calculOccurrence($startDate, $endDate);
		if (count($results) == 0) {
			return null;
		}
		foreach ($results as $result) {
			if (strtotime($result['start']) > strtotime('now') && ($_position == null || $_position == 'start')) {
				if ($_details) {
					return array('date' => $result['start'], 'position' => 'start');
				} else {
					return $result['start'];
				}
			}
			if (strtotime($result['end']) > strtotime('now') && ($_position == null || $_position == 'end')) {
				if ($_details) {
					return array('date' => $result['end'], 'position' => 'end');
				} else {
					return $result['end'];
				}
			}
		}
		return null;
	}

	public function calculOccurrence($_startDate, $_endDate, $_max = 9999999999, $_recurence = 0) {
		if ($_recurence > 5) {
			return [];
		}
		$_recurence++;

		$startTime = $_startDate ? strtotime($_startDate) : strtotime('now - 2 year');
		$endTime = $_endDate ? strtotime($_endDate) : strtotime('now + 2 year');
		$_beginDate = $_startDate ? new DateTime($_startDate) : new DateTime($this->getStartDate());

		$return = [];
		$repeat = $this->getRepeat();

		if ($repeat['enable'] != 1) {
			return $this->handleNonRepeatingEvent($startTime, $endTime);
		}

		$excludeDate = $this->getExcludedDates($repeat, $_startDate, $_endDate, $_max, $_recurence);

		$currentDate = $_beginDate;
		$initStartTime = $this->getTimePart($this->getStartDate());
		$initEndTime = $this->getTimePart($this->getEndDate());

		while ($this->shouldContinue($currentDate, $startTime, $endTime, $repeat)) {
			$formattedDate = $currentDate->format('Y-m-d');

			if ($this->isDateIncluded($formattedDate, $repeat, $excludeDate) && $this->matchesNationalDayCriteria($formattedDate, $repeat)) {
				$return[] = [
					'start' => $currentDate->format('Y-m-d') . ' ' . $initStartTime,
					'end' => $currentDate->format('Y-m-d') . ' ' . $initEndTime,
				];
				if (count($return) >= $_max) {
					return $return;
				}
			}

			$currentDate = $this->calculateNextOccurrence($currentDate, $repeat, $initStartTime, $initEndTime);
		}
		
		return $this->mergeIncludeDates($return, $repeat, $startTime, $endTime, $initStartTime, $initEndTime);
	}

	private function matchesNationalDayCriteria($date, $repeat) {
		$nationalDays = self::getNationalDay(date('Y', strtotime($date)));

		switch ($repeat['nationalDay']) {
			case 'exeptNationalDay':
				return !in_array($date, $nationalDays);
			case 'onlyNationalDay':
				return in_array($date, $nationalDays);
			case 'onlyEven':
				return (date('W', strtotime($date)) % 2) == 0;
			case 'onlyOdd':
				return (date('W', strtotime($date)) % 2) == 1;
			case 'all':  // 'all' means no filter on national day
			default:
				return true;
		}
	}

	private function handleNonRepeatingEvent($startTime, $endTime) {
		if ($this->isEventWithinBounds($startTime, $endTime)) {
			return [[
				'start' => $this->getStartDate(),
				'end' => $this->getEndDate(),
			]];
		}
		return [];
	}

	private function getExcludedDates($repeat, $_startDate, $_endDate, $_max, $_recurence) {
		$excludeDate = [];

		if (!empty($repeat['excludeDate'])) {
			$excludeDate = array_merge($excludeDate, $this->parseExcludeDates($repeat['excludeDate']));
		}

		if (!empty($repeat['excludeDateFromCalendar'])) {
			$excludeDate = array_merge($excludeDate, $this->getExcludeDatesFromCalendar($repeat, $_startDate, $_endDate, $_max, $_recurence));
		}

		return $excludeDate;
	}

	private function parseExcludeDates($excludeDateString) {
		$excludeDate = [];
		foreach (explode(',', $excludeDateString) as $date) {
			$dateRange = explode(':', $date);
			if (count($dateRange) === 2) {
				$excludeDate = array_merge($excludeDate, $this->generateDateRange($dateRange[0], $dateRange[1]));
			} else {
				$excludeDate[] = date('Y-m-d', strtotime($date));
			}
		}
		return $excludeDate;
	}

	private function getExcludeDatesFromCalendar($repeat, $_startDate, $_endDate, $_max, $_recurence) {
		$excludeDates = [];
		$calendar = calendar::byId($repeat['excludeDateFromCalendar']);
		if (is_object($calendar)) {
			$events = $this->getEventsFromCalendar($calendar, $repeat);
			foreach ($events as $event) {
				$occurrences = $event->calculOccurrence($_startDate, $_endDate, $_max, $_recurence);
				foreach ($occurrences as $occurrence) {
					$excludeDates = array_merge($excludeDates, $this->generateDateRange($occurrence['start'], $occurrence['end']));
				}
			}
		}
		return $excludeDates;
	}

	private function generateDateRange($start, $end) {
		$dates = [];
		$startDate = new DateTime($start);
		$endDate = new DateTime($end);

		while ($startDate <= $endDate) {
			$dates[] = $startDate->format('Y-m-d');
			$startDate->modify('+1 day');
		}

		return $dates;
	}

	private function shouldContinue($currentDate, $startTime, $endTime, $repeat) {
		$until = strtotime($this->getUntil());
		return ($until === false || $currentDate->getTimestamp() < $until) && $currentDate->getTimestamp() <= $endTime;
	}

	private function isDateIncluded($date, $repeat, $excludeDate) {
		return !in_array($date, $excludeDate) && $repeat['excludeDay'][date('N', strtotime($date))] == 1;
	}

	private function calculateNextOccurrence($currentDate, $repeat, $initStartTime, $initEndTime) {
		if (isset($repeat['mode']) && $repeat['mode'] === 'advance') {
			// Calculate next occurrence based on "advance" rules.
			$nextMonth = date('F', strtotime('+1 month ' . $currentDate->format('Y-m-d')));
			$year = date('Y', strtotime('+1 month ' . $currentDate->format('Y-m-d')));
			$nextDate = date('Y-m-d', strtotime($repeat['positionAt'] . ' ' . $repeat['day'] . ' of ' . $nextMonth . ' ' . $year));

			if ($nextDate === '1970-01-01') {
				return $currentDate; // Prevent infinite loop if an invalid date is calculated.
			}

			return new DateTime($nextDate . ' ' . $initStartTime);
		}

		// Regular recurrence based on frequency and unit.
		$freq = $repeat['freq'];
		$unit = $repeat['unite'];

		return $currentDate->modify("+$freq $unit");
	}

	private function mergeIncludeDates($occurrences, $repeat, $startTime, $endTime, $initStartTime, $initEndTime) {
		$includeDates = $this->getIncludeDates($repeat, $startTime, $endTime);

		foreach ($includeDates as $includeDate) {
			$includeStart = $includeDate . ' ' . $initStartTime;
			$includeEnd = $includeDate . ' ' . $initEndTime;

			$isDuplicate = false;

			foreach ($occurrences as $occurrence) {
				if ($occurrence['start'] === $includeStart && $occurrence['end'] === $includeEnd) {
					$isDuplicate = true;
					break;
				}
			}

			if (!$isDuplicate) {
				$occurrences[] = [
					'start' => $includeStart,
					'end' => $includeEnd,
				];
			}
		}

		usort($occurrences, function ($a, $b) {
			return strtotime($a['start']) - strtotime($b['start']);
		});

		return $occurrences;
	}

	private function getIncludeDates($repeat, $startTime, $endTime) {
		$includeDates = [];

		// Directly specified include dates
		if (!empty($repeat['includeDate'])) {
			$rawIncludeDates = explode(',', trim($repeat['includeDate'], ','));
			foreach ($rawIncludeDates as $rawDate) {
				if (strpos($rawDate, ':') !== false) {
					list($rangeStart, $rangeEnd) = explode(':', $rawDate);
					$this->addDateRange($rangeStart, $rangeEnd, $includeDates, $startTime, $endTime);
				} else {
					$this->addSingleDate($rawDate, $includeDates, $startTime, $endTime);
				}
			}
		}

		// Include dates from calendar events
		if (!empty($repeat['includeDateFromCalendar'])) {
			$calendar = calendar::byId($repeat['includeDateFromCalendar']);
			if (is_object($calendar)) {
				$events = $repeat['includeDateFromEvent'] === 'all' 
					? self::getEventsByEqLogic($calendar->getId()) 
					: [self::byId($repeat['includeDateFromEvent'])];
				
				foreach ($events as $event) {
					if (is_object($event) && $event->getId() !== $this->getId()) {
						$eventOccurrences = $event->calculOccurrence(null, null);
						foreach ($eventOccurrences as $occurrence) {
							$this->addDateRange($occurrence['start'], $occurrence['end'], $includeDates, $startTime, $endTime);
						}
					}
				}
			}
		}

		return $includeDates;
	}

	private function addDateRange($start, $end, &$dates, $startTime, $endTime) {
		$startDate = strtotime($start);
		$endDate = strtotime($end);

		while ($startDate <= $endDate) {
			if ($startDate >= $startTime && $startDate <= $endTime) {
				$dates[] = date('Y-m-d', $startDate);
			}
			$startDate = strtotime('+1 day', $startDate);
		}
	}

	private function addSingleDate($date, &$dates, $startTime, $endTime) {
		$timestamp = strtotime($date);
		if ($timestamp >= $startTime && $timestamp <= $endTime) {
			$dates[] = date('Y-m-d', $timestamp);
		}
	}

	private function getTimePart($dateTime) {
		return date('H:i:s', strtotime($dateTime));
	}

	private function isEventWithinBounds($startTime, $endTime) {
		return strtotime($this->getStartDate()) <= $endTime && strtotime($this->getEndDate()) >= $startTime;
	}

	public function preSave() {
		if ($this->getEqLogic_id() == '') {
			throw new Exception(__("L'id de l'équipement ne peut être vide", __FILE__));
		}
		if (trim($this->getCmd_param('eventName')) == '') {
			throw new Exception(__("Le nom de l'évènement ne peut être vide", __FILE__));
		}
		$eqLogic = $this->getEqLogic();
		if (!is_object($eqLogic)) {
			throw new Exception(__("Impossible de trouver l'équipement correspondant à l'id", __FILE__) . ' : ' . $this->getEqLogic_id());
		}
		if ((strtotime($this->getStartDate()) + 15) >= strtotime($this->getEndDate())) {
			throw new Exception(__("La date de début d'évènement ne peut être postérieure ou égale à la date de fin", __FILE__));
		}
		$repeat = $this->getRepeat();
		$allEmpty = true;
		foreach ($repeat['excludeDay'] as $day) {
			if ($day == 1) {
				$allEmpty = false;
				break;
			}
		}
		if ($allEmpty) {
			$repeat['excludeDay'][1] = 1;
			$repeat['excludeDay'][2] = 1;
			$repeat['excludeDay'][3] = 1;
			$repeat['excludeDay'][4] = 1;
			$repeat['excludeDay'][5] = 1;
			$repeat['excludeDay'][6] = 1;
			$repeat['excludeDay'][7] = 1;
			$this->setRepeat('excludeDay', $repeat['excludeDay']);
		}

		if ($this->getRepeat('enable') == 1) {
			if ($this->getRepeat('mode') == 'simple') {
				if (!is_numeric($this->getRepeat('freq')) || $this->getRepeat('freq') == '' || $this->getRepeat('freq') <= 0) {
					throw new Exception(__('La fréquence de répétition ne peut être vide, nulle ou négative', __FILE__));
				}
				if ($this->getRepeat('unite') == '') {
					throw new Exception(__("L'unité de répétition ne peut être vide", __FILE__));
				}
			}
		} else {
			$this->setRepeat('freq', 0);
			$this->setUntil('');
		}
		if ($this->getUntil() == '') {
			$this->setUntil(null);
		}
	}

	public function save() {
		return DB::save($this);
	}

	public function dontRemoveCmd() {
		return true;
	}

	public function postSave() {
		$eqLogic = $this->getEqLogic();
		if ($eqLogic->getIsEnable() == 0) {
			$this->setCmd_param('in_progress', 0);
			DB::save($this, true);
			$cmd = $eqLogic->getCmd('info', 'in_progress');
			if (is_object($cmd)) {
				$cmd->event($cmd->execute());
			}
			return;
		}
		$repeat = $this->getRepeat();
		if ($repeat['enable'] == 1) {
			$startDate = (new DateTime('-' . (8 * $repeat['freq']) . ' ' . $repeat['unite'] . ' ' . date('Y-m-d')))->format('Y-m-d H:i:s');
			$endDate = (new DateTime('+' . (99 * $repeat['freq']) . ' ' . $repeat['unite'] . ' ' . date('Y-m-d')))->format('Y-m-d H:i:s');
		} else {
			$startDate = null;
			$endDate = null;
		}
		$this->reschedule();
		$in_progress = $this->getCmd_param('in_progress', 0);
		$this->setCmd_param('in_progress', 0);
		$nowtime = strtotime('now');
		try {
			if (jeedom::isDateOk()) {
				$results = $this->calculOccurrence($startDate, $endDate);
				if (count($results) != 0) {
					for ($i = 0; $i < count($results); $i++) {
						if (strtotime($results[$i]['start']) <= $nowtime && strtotime($results[$i]['end']) > $nowtime) {
							$this->setCmd_param('in_progress', 1);
							if ($in_progress != 1) {
								$this->doAction('start');
							}
							break;
						}
					}
				}
			}
			if ($this->getCmd_param('in_progress', 0) == 0 && $in_progress == 1) {
				$this->doAction('end');
			}
		} catch (Exception $e) {
		}
		DB::save($this, true);
		$cmd = $eqLogic->getCmd('info', 'in_progress');
		if (is_object($cmd)) {
			$cmd->event($cmd->execute());
		}
	}

	public function remove() {
		$cron = cron::byClassAndFunction('calendar', 'pull', array('event_id' => intval($this->getId())));
		if (is_object($cron)) {
			$cron->remove();
		}
		$eqLogic = $this->getEqLogic();
		DB::remove($this);

		$cmd = $eqLogic->getCmd('info', 'in_progress');
		if (is_object($cmd)) {
			$cmd->event($cmd->execute());
		}
	}

	public function doAction($_action = 'start') {
		$eqLogic = $this->getEqLogic();
		if ($eqLogic->getIsEnable() == 0) {
			$this->setCmd_param('in_progress', 0);
			DB::save($this, true);
			return;
		}
		if ($_action == 'start') {
			$this->setCmd_param('in_progress', 1);
			DB::save($this, true);
		}
		if ($_action == 'end') {
			$this->setCmd_param('in_progress', 0);
			DB::save($this, true);
		}
		$eqLogic = $this->getEqLogic();
		$cmd = $eqLogic->getCmd('info', 'in_progress');
		if (is_object($cmd)) {
			$cmd->event($cmd->execute());
		}
		foreach ($this->getCmd_param($_action) as $action) {
			try {
				$options = array();
				if (isset($action['options'])) {
					$options = $action['options'];
				}
				scenarioExpression::createAndExec('action', $action['cmd'], $options);
			} catch (Exception $e) {
				log::add('calendar', 'error', $eqLogic->getHumanName() . __("Erreur lors de l'exécution de", __FILE__) . ' ' . $action['cmd'] . '. ' . __('Détails', __FILE__) . ' : ' . $e->getMessage());
			}
		}
		return true;
	}

	public function hasRight($_right, $_user = null) {
		return $this->getEqLogic()->hasRight($_right, $_user);
	}

	public function getName() {
		if ($this->getCmd_param('eventName') != '') {
			return $this->getCmd_param('eventName');
		} else {
			return $this->getCmd_param('name');
		}
	}

	public function getId() {
		return $this->id;
	}

	public function getStartDate() {
		return $this->startDate;
	}

	public function getEndDate() {
		return $this->endDate;
	}

	public function setId($_id) {
		$this->_changed = utils::attrChanged($this->_changed, $this->id, $_id);
		$this->id = $_id;
		return $this;
	}

	public function setStartDate($_startDate) {
		$this->_changed = utils::attrChanged($this->_changed, $this->startDate, $_startDate);
		$this->startDate = $_startDate;
		return $this;
	}

	public function setEndDate($_endDate) {
		$this->_changed = utils::attrChanged($this->_changed, $this->endDate, $_endDate);
		$this->endDate = $_endDate;
		return $this;
	}

	public function getEqLogic_id() {
		return $this->eqLogic_id;
	}

	public function getEqLogic() {
		return calendar::byId($this->eqLogic_id);
	}

	public function setEqLogic_id($_eqLogic_id) {
		$this->_changed = utils::attrChanged($this->_changed, $this->eqLogic_id, $_eqLogic_id);
		$this->eqLogic_id = $_eqLogic_id;
		return $this;
	}

	public function getRepeat($_key = '', $_default = '') {
		return utils::getJsonAttr($this->repeat, $_key, $_default);
	}

	public function setRepeat($_key, $_value) {
		$repeat = utils::setJsonAttr($this->repeat, $_key, $_value);
		$this->_changed = utils::attrChanged($this->_changed, $this->repeat, $repeat);
		$this->repeat = $repeat;
		return $this;
	}

	public function getUntil() {
		return $this->until;
	}

	public function setUntil($_until) {
		$this->_changed = utils::attrChanged($this->_changed, $this->until, $_until);
		$this->until = $_until;
		return $this;
	}

	public function getCmd_param($_key = '', $_default = '') {
		return utils::getJsonAttr($this->cmd_param, $_key, $_default);
	}

	public function setCmd_param($_key, $_value) {
		$cmd_param = utils::setJsonAttr($this->cmd_param, $_key, $_value);
		$this->_changed = utils::attrChanged($this->_changed, $this->cmd_param, $cmd_param);
		$this->cmd_param = $cmd_param;
		return $this;
	}

	public function getChanged() {
		return $this->_changed;
	}

	public function setChanged($_changed) {
		$this->_changed = $_changed;
		return $this;
	}
}
