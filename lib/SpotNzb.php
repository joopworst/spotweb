<?php
class SpotNzb {
	private $_settings;
	private $_db;
	
	protected $searchForAlternateDownloadUrl = false;
	
	function __construct(SpotDb $db, SpotSettings $settings) {
		$this->_db = $db;
		$this->_settings = $settings;
	} # ctor

	/**
	 * 
	 * Enable or disable searching for alternate urls for the spot.
	 * @param boolean $search
	 */
	public function searchForAlternateDownload($search = true) {
	  $this->searchForAlternateDownloadUrl = $search;
	}
	
	/*
	 * Behandel de gekozen actie voor de NZB file
	 */
	function handleNzbAction($messageids, $userSession, $action, $hdr_spotnntp, $nzb_spotnntp) {
		if (!is_array($messageids)) {
			$messageids = array($messageids);
		} # if
		
		# Controleer de security
		$userSession['security']->fatalPermCheck(SpotSecurity::spotsec_retrieve_nzb, '');
		if ($action != 'display') {
			$userSession['security']->fatalPermCheck(SpotSecurity::spotsec_download_integration, $action);
		} # if
			
		# Haal de volledige spot op en gebruik de informatie daarin om de NZB file op te halen
		$spotsOverview = new SpotsOverview($this->_db, $this->_settings);
		
		$nzbList = array();
		foreach($messageids as $thisMsgId) {
			$fullSpot = $spotsOverview->getFullSpot($thisMsgId, $userSession['user']['userid'], $hdr_spotnntp);
			if (!empty($fullSpot['nzb'])) {
			  
			  $nzb = null;
			  // Search for alternate download urls
				if ($this->searchForAlternateDownloadUrl) {
					$alternateDownload = new SpotAlternateDownload($fullSpot);
					
					// Only return an alternate if there is one.
      	  if ($alternateDownload->hasNzb()) {
      	    $nzb = $alternateDownload->getNzb();
      	  }
			  }

			  // We did not find or search for an alternate download url, fallback to default behaviour.
			  if(!$nzb){
			    $nzb = $spotsOverview->getNzb($fullSpot, $nzb_spotnntp);
			  }
			  
				$nzbList[] = array('spot' => $fullSpot, 'nzb' => $nzb);
			} # if
		} # foreach

		# send nzblist to NzbHandler plugin
		$nzbHandlerFactory = new NzbHandler_Factory();
		$nzbHandler = $nzbHandlerFactory->build($this->_settings, $action, $userSession['user']['prefs']['nzbhandling']);

		$nzbHandler->processNzb($fullSpot, $nzbList);

		# en voeg hem toe aan de lijst met downloads
		if ($userSession['user']['prefs']['keep_downloadlist']) {
			if ($userSession['security']->allowed(SpotSecurity::spotsec_keep_own_downloadlist, '')) {
				foreach($messageids as $thisMsgId) {
					$this->_db->addToDownloadList($thisMsgId, $userSession['user']['userid']);
				} # foreach
			} # if
		} # if

		# en verstuur een notificatie
		$spotsNotifications = new SpotNotifications($this->_db, $this->_settings, $userSession);
		$spotsNotifications->sendNzbHandled($action, $fullSpot);
	} # handleNzbAction
	
} # SpotNzb
