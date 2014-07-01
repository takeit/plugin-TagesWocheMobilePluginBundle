<?php
/**
 * @package Newscoop
 * @author  Mischa Gorinskat <mischa.gorinskat@sourcefabric.org>
 * @copyright 2014 Sourcefabric o.p.s.
 */

namespace Newscoop\TagesWocheMobilePluginBundle\Subscription;

use DateTime;
use SimpleXmlElement;
use Exception;
use Guzzle\Http\Exception\RequestException;
use Guzzle\Http\Exception\ClientErrorResponseException;
use Newscoop\Entity\User;
use Newscoop\Http\ClientFactory;

/**
 * Verlags Manager Service
 */
class VerlagsManagerService
{
    const CID = 'customer_id';
    const CUSTOMER_URL = 'https://www.tageswoche.ch/ftp/subscriptions/{customer}.xml';
    const SUBSCRIBER_URL = 'https://abo.tageswoche.ch/dmpro/ws/subscriber/NMBA/{subscriber}{?userkey}';
    const TEST_URL = 'https://www.tageswoche.ch/ftp/subscriptions/demo/{customer}.xml';

    /**
     * @var bool
     */
    private $testConnection;

    /**
     * @var array
     */
    private $activeStatusCodes = array(1, 2, 3);

    /**
     * @param Newscoop\Http\ClientFactory $clientFactory
     */
    public function __construct(ClientFactory $clientFactory)
    {
        $this->clientFactory = $clientFactory;
    }

    /**
     * Get customer view
     *
     * @param Newscoop\Entity\User $user
     *
     * @return Newscoop\TagesWocheMobilePluginBundle\Subscription\CustomerView
     */
    public function getView(User $user)
    {
        $subscriber = $this->findSubscriber($user);
        if (!isset($subscriber)) {
            return new CustomerView();
        }

        $view = new CustomerView();
        $view->customer_id = $user->getAttribute(self::CID) ?: $user->getSubscriber();
        $view->subcodes = $this->getSubcodes($subscriber);
        $view->customer_id_subcode = in_array($view->customer_id, $view->subcodes);
        if ($view->customer_id_subcode) {
            $view->master_id = (string) $subscriber->uniqueId;
        }

        $validUntil = $this->getMax($subscriber);
        if ($validUntil) {
            $view->print_subscription = true;
            $view->print_subscription_valid_until = $validUntil;
        }

        return $view;
    }

    /**
     * Test if given id is valid customer id
     *
     * @param string $cid
     * @return bool
     */
    public function isCustomer($cid)
    {
        $subscriber = $this->findByCustomer($cid);
        return isset($subscriber) && $this->getMax($subscriber) !== null;
    }

    /**
     * Find max valid time from active subscriptions
     *
     * @param SimpleXmlElement $subscriber
     * @return DateTime
     */
    public function getMax(SimpleXmlElement $subscriber)
    {
        $subscriptions = $subscriber->subscriptions->xpath('subscription');

        $statusCodes = $this->activeStatusCodes;
        $activeSubscriptions = array_filter($subscriptions, function ($subscription) use ($statusCodes) {
            return in_array((int) $subscription->statusCode, $statusCodes);
        });

        $dates = array_map(function ($subscription) {
            $paidUntil = $subscription->xpath('expectedPaidUntil');

            if (empty($paidUntil)) {
                $paidUntil = $subscription->paidUntil;
            } else {
                $paidUntil = array_pop($paidUntil);
            }

            return DateTime::createFromFormat('dmy', $paidUntil)->format('Y-m-d');
        }, $activeSubscriptions);

        if (!empty($dates)) {
            return DateTime::createFromFormat('Y-m-d', max($dates));
        }
    }

    /**
     * Find subscriber info for given user
     *
     * @param Newscoop\Entity\User $user
     * @return SimpleXmlElement
     */
    public function findSubscriber(User $user)
    {
        if ($user->getAttribute(self::CID)) {
            return $this->findByCustomer($user->getAttribute(self::CID));
        } elseif ($user->getSubscriber()) {
            throw new VerlagsManagerException('Searching by subscriber not supported by Verlags Manager.');
        }
    }

    /**
     * Find subscriber by customer id
     *
     * @param string $customer
     * @return SimpleXmlElement
     */
    public function findByCustomer($customer)
    {
        if (preg_match('/.{4}\-.{2}\-.{2}/', $customer)) {
            $expectedPaidUntil = date('dmy', strtotime('+1 day'));
            $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<subscribers>
    <subscriber>
        <uniqueId>$customer</uniqueId>
        <subscriptions>
            <subscription>
                <expectedPaidUntil>$expectedPaidUntil</expectedPaidUntil>
                <statusCode>1</statusCode>
            </subscription>
        </subscriptions>
    </subscriber>
</subscribers>
XML;
            $xmlObj = new \SimpleXmlElement($xml);
            return $xmlObj->subscriber;
        } else {
            return $this->fetchXml(array(
                self::CUSTOMER_URL,
                array('customer' => $customer),
            ));
        }
    }

    /**
     * Fetch xml info via client
     *
     * @param array $params
     * @return SimpleXmlElement
     */
    private function fetchXml(array $params)
    {
        try {
            $client = $this->clientFactory->getClient();
            $getRequest = $client->get($params);
            $response = $getRequest->send();
        } catch(ClientErrorResponseException $e){
            if ($getRequest->getResponse()->getStatusCode() == '404') {
                return null;
            }
            throw new VerlagsManagerException($e->getMessage());
        } catch (RequestException $e) {
            throw new VerlagsManagerException($e->getMessage());
        }

        if (!$response->isSuccessful()) {
            throw new VerlagsManagerException();
        }

        $xml = simplexml_load_string($response->getBody(true));
        return isset($xml->subscriber) ? $xml->subscriber : null;
    }

    /**
     * Test connection
     *
     * @return bool
     */
    public function testConnection()
    {
        if ($this->testConnection === null) {
            try {
                $client = new \Zend_Http_Client(self::TEST_URL);
                $response = $client->request();
                $this->testConnection = $response->isSuccessful() || $response->getStatus() == 405;
            } catch (Exception $e) {
                $this->testConnection = false;
            }
        }

        return $this->testConnection;
    }

    /**
     * Generate user key
     *
     * @param Newscoop\Entity\User $user
     * @return string
     */
    public function generateKey(User $user)
    {
        $subscriber = $this->findSubscriber($user);
        if (!$subscriber) {
            return;
        }

        try {
            $key = md5($user->getId().$user->getEmail().time());
            $client = $this->clientFactory->getClient();
            $response = $client->put(array(
                self::SUBSCRIBER_URL,
                array(
                    'subscriber' => (string) $subscriber->subscriberId,
                    'userkey' => $key,
                ),
            ))->send();
            return $response->isSuccessful() ? $key : null;
        } catch (Exception $e) {
            return;
        }
    }

    /**
     * Get subcodes
     *
     * @param SimpleXmlElement $subscriber
     * @return array
     */
    private function getSubcodes(SimpleXmlElement $subscriber)
    {
        $subcodes = array();
        for ($i = 1; $i <= 5; $i++) {
            $key = sprintf("appId%d", $i);
            if (isset($subscriber->$key)) {
                $subcodes[] = (string) $subscriber->$key;
            }
        }

        return $subcodes;
    }
}
