<?php
/*
 MIT License
 Copyright (c) 2010 - 2014 Daniel Hoffend, Peter Petermann

 Permission is hereby granted, free of charge, to any person
 obtaining a copy of this software and associated documentation
 files (the "Software"), to deal in the Software without
 restriction, including without limitation the rights to use,
 copy, modify, merge, publish, distribute, sublicense, and/or sell
 copies of the Software, and to permit persons to whom the
 Software is furnished to do so, subject to the following
 conditions:

 The above copyright notice and this permission notice shall be
 included in all copies or substantial portions of the Software.

 THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 OTHER DEALINGS IN THE SOFTWARE.
*/

namespace Pheal\Access;

/**
 * check access modules. Check if the given keyinfo allows a specific api call
 * access bits are static for performance reason. Feel free to write your own version
 * if you like to check api->calllist live.
 *
 * new/unknown api calls are allowed by default.
 */

use Pheal\Exceptions\AccessException;

class StaticAccess implements CanAccess
{
    /**
     * Key Type of the given API key
     * @var string (Account, Character, Corporation)
     */
    protected $keyType = null;

    /**
     * accessMask for this API Key
     * @var int
     */
    protected $accessMask = 0;

    /**
     * Database of calls to check against the given keyinfo
     * list based on pheal()->apiScope->calllist()
     * with manually added information
     * @var array
     */
    protected static $bits = [
        'char' => [
            'contracts' => ['Character', 67108864],
            'wallettransactions' => ['Character', 4194304],
            'walletjournal' => ['Character', 2097152],
            'upcomingcalendarevents' => ['Character', 1048576],
            'standings' => ['Character', 524288],
            'skillqueue' => ['Character', 262144],
            'skillintraining' => ['Character', 131072],
            'research' => ['Character', 65536],
            'notificationtexts' => ['Character', 32768],
            'notifications' => ['Character', 16384],
            'medals' => ['Character', 8192],
            'marketorders' => ['Character', 4096],
            'mailmessages' => ['Character', 2048],
            'mailinglists' => ['Character', 1024],
            'mailbodies' => ['Character', 512],
            'killlog' => ['Character', 256],
            'industryjobs' => ['Character', 128],
            'facwarstats' => ['Character', 64],
            'contactnotifications' => ['Character', 32],
            'contactlist' => ['Character', 16],
            'charactersheet' => ['Character', 8],
            'calendareventattendees' => ['Character', 4],
            'assetlist' => ['Character', 2],
            'accountbalance' => ['Character', 1]
        ],
        'account' => [
            'accountstatus' => ['Character', 33554432]
        ],
        'corp' => [
            'contracts' => ['Corporation', 8388608],
            'titles' => ['Corporation', 4194304],
            'wallettransactions' => ['Corporation', 2097152],
            'walletjournal' => ['Corporation', 1048576],
            'starbaselist' => ['Corporation', 524288],
            'standings' => ['Corporation', 262144],
            'starbasedetail' => ['Corporation', 131072],
            'shareholders' => ['Corporation', 65536],
            'outpostservicedetail' => ['Corporation', 32768],
            'outpostlist' => ['Corporation', 16384],
            'medals' => ['Corporation', 8192],
            'marketorders' => ['Corporation', 4096],
            'membertracking' => ['Corporation', 2048],
            'membersecuritylog' => ['Corporation', 1024],
            'membersecurity' => ['Corporation', 512],
            'killlog' => ['Corporation', 256],
            'industryjobs' => ['Corporation', 128],
            'facwarstats' => ['Corporation', 64],
            'containerlog' => ['Corporation', 32],
            'contactlist' => ['Corporation', 16],
            'corporationsheet' => ['Corporation', 8],
            'membermedals' => ['Corporation', 4],
            'assetlist' => ['Corporation', 2],
            'accountbalance' => ['Corporation', 1]
        ]

        // characterinfo is a public call with more details if you've better api keys
        // no detailed configuration needed atm
        /*
        'eve' => array(
            'characterinfo'           => array('Character', array(16777216, 8388608))
        )
        */
    ];

    /**
     * Check if the api key is allowed to make this api call
     * @param string $scope
     * @param string $name
     * @param string $keyType
     * @param int $accessMask
     * @throws \Pheal\Exceptions\AccessException
     * @return bool
     */
    public function check($scope, $name, $keyType, $accessMask)
    {
        // there's no "Account" type on the access bits level
        $type = ($keyType == 'Account') ? 'Character' : $keyType;

        // no keyinfo configuration found
        // assume it's a public call or it's not yet defined
        // allow and let the CCP decide
        if (!$keyType
            || !in_array($type, array('Character', 'Corporation'))
            || !isset($this->bits[strtolower($scope)][strtolower($name)])
        ) {
            return true;
        }

        // check accessLevel
        $check = $this->bits[strtolower($scope)][strtolower($name)];

        // check if keytype is correct for this call
        if ($check[0] == $type) {
            // check single accessbit
            if (is_int($check[1]) && (int)$accessMask & (int)$check[1]) {
                return true;
            }

            // fix if multiple accessbits are valid (eve/CharacterInfo)
            //elseif(is_array($check[1]))
            //    foreach($check[1] as $checkbit)
            //        if($checkbit && $checkbit & $accessMask)
            //            return true;
        }

        // no match == no access right found.
        throw new AccessException(
            sprintf(
                'Pheal blocked an API call (%s/%s) which is not allowed by the given keyType/accessMask (%s/%d)',
                $scope,
                $name,
                $keyType,
                $accessMask
            )
        );
    }
}
