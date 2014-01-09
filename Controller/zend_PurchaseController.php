<?php
/**
 * @package Newscoop
 * @copyright 2012 Sourcefabric o.p.s.
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

require_once __DIR__ . '/AbstractController.php';

use Newscoop\Entity\User;
use Tageswoche\TageswocheException;
use Tageswoche\Subscription\DmproException;

/**
 * Purchage API
 */
class Api_PurchaseController extends AbstractController
{
    /**
     * Validate purchase
     */
    public function validateAction()
    {
        $this->assertIsSecure();

        $receipt = $this->_getParam('receipt_data');
        if (empty($receipt)) {
            $this->sendError("No 'receipt_data' provided");
        }

        try {
            $data = $this->_helper->service('mobile.purchase')->validate($receipt);
        } catch (Exception $e) {
            $this->sendError('Can not validate ticket', 403);
        }

        if (!$data['receipt_valid']) {
            $this->sendError('Payment required', 402);
        }

        if ($this->hasAuthInfo()) {
            $user = $this->getUser();
            if ($data['receipt_valid']) {
                try {
                    $this->_helper->service('user_subscription')->upgrade($user);
                } catch (DmproException $e) {
                    $this->sendError('Dmpro service error.', 500);
                } catch (TageswocheException $e) {
                    $this->sendError('No way to upgrade', 403);
                }
            }
        }

        $this->_helper->json($data);
    }

    /**
     * Check free upgrade status
     */
    public function freeupgradeAction()
    {
        $this->assertIsSecure();

        try {
            $user = $this->getUser();
            $this->_helper->service('user_subscription')->freeUpgrade($user);
            $this->sendError('OK', 200);
        } catch (DmproException $e) {
            $this->sendError('Dmpro service error.', 500);
        } catch (TageswocheException $e) {
            $this->sendError('No way to upgrade', 403);
        }
    }
}
