<?php

require_once('system/cms/libraries/Google.php');

/**
 * Google Analytics PHP API
 *
 * This class can be used to retrieve data from the Google Analytics API with PHP
 * It fetches data as array for use in applications or scripts
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * Credits: http://www.alexc.me/
 * parsing the profile XML to a PHP array
 *
 *
 * @link http://www.swis.nl
 * @copyright 2009 SWIS BV
 * @author Vincent Kleijnendorst - SWIS BV (vkleijnendorst [AT] swis [DOT] nl)
 *
 * @version 0.1
 */
class Analytics {

	private $_sUser;
	private $_sPass;
	private $_sAuth;
	private $_sProfileId;
	private $_sStartDate;
	private $_sEndDate;
	private $_bUseCache;
	private $_iCacheAge;
	private $_client;

	/**
	 * public constructor
	 *
	 * @param string $sUser
	 * @param string $sPass
	 * @return analytics
	 */
	public function __construct($params = array())
	{
		$this->_sUser = $params['username'];
		$this->_sPass = $params['password'];

		$this->_bUseCache = false;

		$this->_client = new Google();

		$this->auth();
	}

	/**
	 * Google Authentification, returns session when set
	 */
	private function auth()
	{
		
		if (isset($_SESSION['auth']) && $_SESSION['ga_auth_expiration'] <= time())
		{
			$this->_client->setAccessToken($_SESSION['auth']);
			$this->_sAuth = $_SESSION['auth'];
			return;
		}

		require_once('system/cms/libraries/Google.php');

		$client_id = 'CLIENT ID'; //Client ID
		$service_account_name = 'SERVICE ACCOUNT MAIL ADDRESS'; //Email Address
		$key_file_location = '/path/to/p12/file.p12'; //key.p12

		$key = file_get_contents($key_file_location);

		$cred = new Google_Auth_AssertionCredentials(
		    $service_account_name,
		    array('https://www.googleapis.com/auth/analytics.readonly'),
		    $key
		);

		$this->_client->setAssertionCredentials($cred);

		if ($this->_client->getAuth()->isAccessTokenExpired()) {
		  $this->_client->getAuth()->refreshTokenWithAssertion($cred);
		}

		$auth_data = json_decode($this->_client->getAccessToken());

		$_SESSION['auth'] = $auth_data->access_token;
		$_SESSION['ga_auth_expiration'] = $auth_data->created + $auth_data->expires_in;

		$this->_sAuth = $_SESSION['auth'];
	}

	/**
	 * Use caching (bool)
	 * Whether or not to store GA data in a session for a given period
	 *
	 * @param bool $bCaching (true/false)
	 * @param int $iCacheAge seconds (default: 10 minutes)
	 */
	public function useCache($bCaching = true, $iCacheAge = 600)
	{
		$this->_bUseCache = $bCaching;
		$this->_iCacheAge = $iCacheAge;
		if ($bCaching && !isset($_SESSION['cache']))
		{
			$_SESSION['cache'] = array();
		}
	}


	/**
	 * Sets GA Profile ID  (Example: ga:12345)
	 */
	public function setProfileById($sProfileId)
	{
		$this->_sProfileId = $sProfileId;
	}

	/**
	 * get resulsts from cache if set and not older then cacheAge
	 *
	 * @param string $sKey
	 * @return mixed cached data
	 */
	private function getCache($sKey)
	{
		if ($this->_bUseCache === false)
		{
			return false;
		}

		if (!isset($_SESSION['cache'][$this->_sProfileId]))
		{
			$_SESSION['cache'][$this->_sProfileId] = array();
		}
		if (isset($_SESSION['cache'][$this->_sProfileId][$sKey]))
		{
			if (time() - $_SESSION['cache'][$this->_sProfileId][$sKey]['time'] < $this->_iCacheAge)
			{
				return $_SESSION['cache'][$this->_sProfileId][$sKey]['data'];
			}
		}
		return false;
	}

	/**
	 * Cache data in session
	 *
	 * @param string $sKey
	 * @param mixed $mData Te cachen data
	 */
	private function setCache($sKey, $mData)
	{

		if ($this->_bUseCache === false)
		{
			return false;
		}

		if ( ! isset($_SESSION['cache'][$this->_sProfileId]))
		{
			$_SESSION['cache'][$this->_sProfileId] = array();
		}
		$_SESSION['cache'][$this->_sProfileId][$sKey] = array('time' => time(),
			'data' => $mData);
	}

	public function getData($aProperties = array())
	{
		$aCache = $this->getCache($this->_sProfileId . $this->_sStartDate . $this->_sEndDate);

		if ($aCache !== false)
		{
			return $aCache;
		}

		$service = new Google_Service_Analytics($this->_client);

		$metrics = $aProperties['metrics'];
		unset($aProperties['metrics']);

		$gaData = $service->data_ga->get($this->_sProfileId, $this->_sStartDate, $this->_sEndDate, $metrics, $aProperties);

		// cache the results (if caching is true)
		$this->setCache($this->_sProfileId . $this->_sStartDate . $this->_sEndDate, $gaData);

		return $gaData;
	}

	/**
	 * Sets the date range for GA data
	 *
	 * @param string $sStartDate (YYY-MM-DD)
	 * @param string $sEndDate   (YYY-MM-DD)
	 */
	public function setDateRange($sStartDate, $sEndDate)
	{
		$this->_sStartDate = $sStartDate;
		$this->_sEndDate = $sEndDate;
	}

	/**
	 * Sets de data range to a given month
	 *
	 * @param int $iMonth
	 * @param int $iYear
	 */
	public function setMonth($iMonth, $iYear)
	{
		$this->_sStartDate = date('Y-m-d', strtotime($iYear . '-' . $iMonth . '-01'));
		$this->_sEndDate = date('Y-m-d', strtotime($iYear . '-' . $iMonth . '-' . date('t', strtotime($iYear . '-' . $iMonth . '-01'))));
	}

	/**
	 * Get visitors for given period
	 *
	 */
	public function getVisitors()
	{
		$gaData = $this->getData(array(
			'dimensions' => 'ga:date',
			'metrics' => 'ga:visits',
			'sort' => 'ga:date'
		))->getRows();

		$aData = array();

		foreach($gaData as $row)
		{
			$aData[$row[0]] = $row[1];
		}

		return $aData;
	}

	/**
	 * Get pageviews for given period
	 *
	 */
	public function getPageviews()
	{
		$gaData = $this->getData(array(
			'dimensions' => 'ga:date',
			'metrics' => 'ga:pageviews',
			'sort' => 'ga:date'
		))->getRows();

		$aData = array();

		foreach($gaData as $row)
		{
			$aData[$row[0]] = $row[1];
		}

		return $aData;
	}

	/**
	 * Get pageviews for given period
	 *
	 */
	public function getTimeOnSite()
	{
		return $this->getData(array(
			'dimensions' => 'ga:date',
			'metrics' => 'ga:timeOnSite',
			'sort' => 'ga:date'
		));
	}

	/**
	 * Get visitors per hour for given period
	 *
	 */
	public function getVisitsPerHour()
	{
		return $this->getData(array(
			'dimensions' => 'ga:hour',
			'metrics' => 'ga:visits',
			'sort' => 'ga:hour'
		));
	}

	/**
	 * Get Browsers for given period
	 *
	 */
	public function getBrowsers()
	{
		$aData = $this->getData(array(
		   'dimensions' => 'ga:browser,ga:browserVersion',
			'metrics' => 'ga:visits',
			'sort' => 'ga:visits'
		));
		arsort($aData);
		return $aData;
	}

	/**
	 * Get Operating System for given period
	 *
	 */
	public function getOperatingSystem()
	{
		$aData = $this->getData(array(
			'dimensions' => 'ga:operatingSystem',
			'metrics' => 'ga:visits',
			'sort' => 'ga:visits'
		));
		// sort descending by number of visits
		arsort($aData);
		return $aData;
	}

	/**
	 * Get screen resolution for given period
	 *
	 */
	public function getScreenResolution()
	{
		$aData = $this->getData(array(
			'dimensions' => 'ga:screenResolution',
			'metrics' => 'ga:visits',
			'sort' => 'ga:visits'
		));

		// sort descending by number of visits
		arsort($aData);
		return $aData;
	}

	/**
	 * Get referrers for given period
	 *
	 */
	public function getReferrers()
	{
		$aData = $this->getData(array(
			'dimensions' => 'ga:source',
			'metrics' => 'ga:visits',
			'sort' => 'ga:source'
		));

		// sort descending by number of visits
		arsort($aData);
		return $aData;
	}

	/**
	 * Get search words for given period
	 *
	 */
	public function getSearchWords()
	{
		$aData = $this->getData(array(
			'dimensions' => 'ga:keyword',
			'metrics' => 'ga:visits',
			'sort' => 'ga:keyword'
		));
		// sort descending by number of visits
		arsort($aData);
		return $aData;
	}
}