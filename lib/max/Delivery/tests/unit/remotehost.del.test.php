<?php

/*
+---------------------------------------------------------------------------+
| Revive Adserver                                                           |
| http://www.revive-adserver.com                                            |
|                                                                           |
| Copyright: See the COPYRIGHT.txt file.                                    |
| License: GPLv2 or later, see the LICENSE.txt file.                        |
+---------------------------------------------------------------------------+
*/

require_once MAX_PATH . '/lib/max/Delivery/remotehost.php';

/**
 * A class for testing the remoteshost.php functions.
 *
 * @package    MaxDelivery
 * @subpackage TestSuite
 */
class Test_DeliveryRemotehost extends UnitTestCase
{
    /**
     * The constructor method.
     */
    public function __construct()
    {
        parent::__construct();
    }


    /**
     * @todo future test case
     * A function to convert the $_SERVER['REMOTE_ADDR'] global variable
     * from the current value to the real remote viewer's value, should
     * that viewer be coming via an HTTP proxy.
     *
     * Only performs this conversion if the option to do so is set in the
     * configuration file.
     */
    public function test_MAX_remotehostProxyLookup()
    {
        $serverSave = $_SERVER;

        // This $_SERVER dump was provided by a user running HAProxy
        $sampleSERVER = [
            'HTTP_HOST' => 'max.i12.de',
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (X11; U; Linux i686; en-GB; rv:1.8.1.5) Gecko/20070718 Fedora/2.0.0.5-1.fc7 Firefox/2.0.0.5',
            'HTTP_ACCEPT' => 'text/xml,application/xml,application/xhtml+xml,text/html;q=0.9,text/plain;q=0.8,image/png,*/*;q=0.5',
            'HTTP_ACCEPT_LANGUAGE' => 'en-gb,en;q=0.5',
            'HTTP_ACCEPT_ENCODING' => 'gzip,deflate',
            'HTTP_ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.7',
            'HTTP_KEEP_ALIVE' => '300',
            'HTTP_COOKIE' => 'phpAds_id=abcdef1234567890acdef1234567890a',
            'HTTP_CONNECTION' => 'close',
            'PATH' => '/usr/bin:/bin:/usr/sbin:/sbin',
            'SERVER_SIGNATURE' => '<address>Apache/2.0.59 (Unix) Server at max.i12.de Port 80</address>',
            'SERVER_SOFTWARE' => 'Apache/2.0.59 (Unix)',
            'SERVER_NAME' => 'dev.openx.org',
            'SERVER_ADDR' => '10.0.0.1',
            'SERVER_PORT' => '80',
            'REMOTE_ADDR' => '10.0.0.2',
            'DOCUMENT_ROOT' => '/var/www/html/live-openads',
            'SERVER_ADMIN' => 'bugs@openads.org',
            'SCRIPT_FILENAME' => '/var/www/html/live-openads/lib/max/Delivery/tests/unit/remotehost.del.test.php',
            'REMOTE_PORT' => '49083',
            'GEOIP_CONTINENT_CODE' => '--',
            'GEOIP_COUNTRY_CODE' => '--',
            'GEOIP_COUNTRY_NAME' => 'N/A',
            'GEOIP_DMA_CODE' => '0',
            'GEOIP_AREA_CODE' => '0',
            'GEOIP_LATITUDE' => '0.000000',
            'GEOIP_LONGITUDE' => '0.000000',
            'GEOIP_ISP' => 'Nildram',
            'GATEWAY_INTERFACE' => 'CGI/1.1',
            'SERVER_PROTOCOL' => 'HTTP/1.1',
            'REQUEST_METHOD' => 'GET',
            'QUERY_STRING' => '',
            'REQUEST_URI' => '/lib/max/Delivery/tests/unit/remotehost.del.test.php',
            'SCRIPT_NAME' => '/lib/max/Delivery/tests/unit/remotehost.del.test.php',
            'PHP_SELF' => '/lib/max/Delivery/tests/unit/remotehost.del.test.php',
            'REQUEST_TIME' => time(),
        ];
        // I am unsure if this is a bug in OpenX or HAProxy, but the above dump does not contain
        // either an HTTP_VIA/REMOTE_HOST header, therefore OpenX assumes this is not proxied
        // I am adding it to "fix" the test

        $GLOBALS['_MAX']['CONF']['logging']['proxyLookup'] = true;

        // Check if just HTTP_VIA in the above array:
        $_SERVER = $sampleSERVER;
        $_SERVER['HTTP_VIA'] = '194.85.1.1 (Squid/2.4.STABLE7)';
        $_SERVER['HTTP_FORWARDED_FOR'] = '124.124.124.124';

        $return = MAX_remotehostProxyLookup();
        $this->assertTrue($_SERVER['REMOTE_ADDR'] == $_SERVER['HTTP_FORWARDED_FOR']);

        // Test with 'HTTP_X_FORWARDED_FOR' instead of via
        $_SERVER = $sampleSERVER;
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '125.125.125.125';

        $return = MAX_remotehostProxyLookup();
        $this->assertTrue($_SERVER['REMOTE_ADDR'] == $_SERVER['HTTP_X_FORWARDED_FOR']);

        // Check that if neither are set, then the remotehost lookup entry is performed
        $_SERVER = $sampleSERVER;
        $_SERVER['REMOTE_HOST'] = 'my.proxy.com';
        $_SERVER['HTTP_CLIENT_IP'] = '126.126.126.126';

        $return = MAX_remotehostProxyLookup();
        $this->assertTrue($_SERVER['REMOTE_ADDR'] == $_SERVER['HTTP_CLIENT_IP']);

        // Check that with multiple X_FORWARDED_FOR entries, the leftmost value is used
        $_SERVER = $sampleSERVER;
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '111.111.111.111, 222.222.222.222';
        $return = MAX_remotehostProxyLookup();
        $this->assertTrue($_SERVER['REMOTE_ADDR'] == '111.111.111.111');

        // Check that with multiple X_FORWARDED_FOR entries, the leftmost NON-PRIVATE value is used
        $_SERVER = $sampleSERVER;
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '10.10.10.10, 111.111.111.111, 222.222.222.222';
        $return = MAX_remotehostProxyLookup();
        $this->assertTrue($_SERVER['REMOTE_ADDR'] == '111.111.111.111');

        // Check that with multiple X_FORWARDED_FOR entries, the leftmost NON-PRIVATE value is used
        $_SERVER = $sampleSERVER;
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '10.10.10.10, 192.168.1.1, 111.111.111.111, 222.222.222.222';
        $return = MAX_remotehostProxyLookup();
        $this->assertTrue($_SERVER['REMOTE_ADDR'] == '111.111.111.111');

        $_SERVER = $serverSave;
    }

    public function test_MAX_remotehostAnonymise()
    {
        $GLOBALS['_MAX']['CONF']['privacy']['anonymiseIp'] = false;
        $_SERVER['REMOTE_ADDR'] = '127.0.0.127';
        MAX_remotehostAnonymise();
        $this->assertEqual($_SERVER['REMOTE_ADDR'], '127.0.0.127');

        $GLOBALS['_MAX']['CONF']['privacy']['anonymiseIp'] = true;
        $_SERVER['REMOTE_ADDR'] = '127.0.0.127';
        MAX_remotehostAnonymise();
        $this->assertEqual($_SERVER['REMOTE_ADDR'], '127.0.0.0');
    }

    /**
     * @todo future test case
     * A function to perform a reverse lookup of the hostname from the IP address,
     * and store the result in the $_SERVER['REMOTE_HOST'] global variable.
     *
     * Only performs the reverse lookup if the option is set in the configuration,
     * and if the host name is not already present. If the the host name is not
     * present and the option to perform the lookup is not set, then the host name
     * is set to the remote IP address instead.
     */
    public function test_MAX_remotehostReverseLookup()
    {
        $return = MAX_remotehostReverseLookup();
        $this->assertTrue(true);
    }

    /**
     * @todo concerns a plugin
     *
     * A function to set the viewer's geotargeting information in the
     * $GLOBALS['_MAX']['CLIENT_GEO'] global variable, if a plugin for
     * geotargeting information is configured.
     *
     * @todo This is a workaround to avoid having to include the entire plugin architecure
     *       just to be able to load the config information. The plugin system should be
     *       refactored to allow the Delivery Engine to load the information independently
     */
    public function test_MAX_remotehostSetGeoInfo()
    {
        $return = MAX_remotehostSetGeoInfo();
        $this->assertTrue(true);
    }

    /**
     * A function to determine if a given IP address is in a private network or
     * not.
     *
     * @param string $ip The IP address to check.
     * @return boolean Returns true if the IP address is in a private network,
     *                 false otherwise.
     */
    public function test_MAX_remotehostPrivateAddress()
    {
        $return = MAX_remotehostPrivateAddress('127.0.0.1');
        $this->assertTrue($return);
        $return = MAX_remotehostPrivateAddress('127.10.0.2');
        $this->assertTrue($return);
        $return = MAX_remotehostPrivateAddress('10.1.0.23');
        $this->assertTrue($return);
        $return = MAX_remotehostPrivateAddress('172.16.0.0');
        $this->assertTrue($return);
        $return = MAX_remotehostPrivateAddress('172.31.255.255');
        $this->assertTrue($return);

        $return = MAX_remotehostPrivateAddress('172.15.255.255');
        $this->assertFalse($return);
        $return = MAX_remotehostPrivateAddress('172.32.0.1');
        $this->assertFalse($return);
        $return = MAX_remotehostPrivateAddress('8.8.8.8');
        $this->assertFalse($return);
    }
}
