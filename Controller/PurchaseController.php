<?php
/**
 * @package Newscoop
 * @copyright 2012 Sourcefabric o.p.s.
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace Newscoop\TagesWocheMobilePluginBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Newscoop\Entity\Article;
use Newscoop\Entity\User;
use Tageswoche\TageswocheException;
use Tageswoche\Subscription\DmproException;

/**
 * Purchage API
 */
class PurchaseController extends Controller
{
    /**
     * Route("/validate")
     */
    public function validateAction()
    {
        $apiHelperService = $this->container->get('newscoop_tageswochemobile_plugin.api_helper');

        if (!$apiHelperService->isSecure()) {
            return $apiHelperService->sendError('Secure connection required', 400);
        }

        $receipt = $request->query->get('receipt_data');
        if (empty($receipt)) {
            return $apiHelperService->sendError("No 'receipt_data' provided");
        }

        try {
            $data = $this->container->get('newscoop_tageswochemobile_plugin.mobile.purchase')->validate($receipt);
        } catch (Exception $e) {
            return $apiHelperService->sendError('Can not validate ticket', 403);
        }

        if (!$data['receipt_valid']) {
            return $apiHelperService->sendError('Payment required', 402);
        }

        if ($apiHelperService->hasAuthInfo()) {
            $user = $apiHelperService->getUser();
            if (!($user instanceof User)) {
                return $user !== null ? $user : $apiHelperService->sendError('Invalid credentials.', 401);
            }

            if ($data['receipt_valid']) {
                try {
                    $this->container->get('newscoop_tageswochemobile_plugin.user_subscription')->upgrade($user);
                } catch (DmproException $e) {
                    return $apiHelperService->sendError('Dmpro service error.', 500);
                } catch (TageswocheException $e) {
                    return $apiHelperService->sendError('No way to upgrade', 403);
                }
            }
        }

        return new JsonResponse($data);
    }

    /**
     * @Route("/free_upgrade")
     */
    public function freeUpgradeAction()
    {
        $apiHelperService = $this->container->get('newscoop_tageswochemobile_plugin.api_helper');

        if (!$apiHelperService->isSecure()) {
            return $apiHelperService->sendError('Secure connection required', 400);
        }

        try {
            $user = $apiHelperService->getUser();
            if (!($user instanceof User)) {
                return $user !== null ? $user : $apiHelperService->sendError('Invalid credentials.', 401);
            }

            $this->container->get('newscoop_tageswochemobile_plugin.user_subscription')->freeUpgrade($user);
            return $apiHelperService->sendError('OK', 200);
        } catch (DmproException $e) {
            return $apiHelperService->sendError('Dmpro service error.', 500);
        } catch (TageswocheException $e) {
            return $apiHelperService->sendError('No way to upgrade', 403);
        }
    }
}
