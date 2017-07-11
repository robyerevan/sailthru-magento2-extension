<?php

namespace Sailthru\MageSail\Plugin;

use Magento\Framework\Exception\LocalizedException;
use Magento\Newsletter\Model\Subscriber;
use Sailthru\MageSail\Helper\ClientManager;
use Sailthru\MageSail\Helper\Settings as SailthruSettings;

class SubscribeIntercept
{

    public function __construct(ClientManager $clientManager, SailthruSettings $sailthruSettings)
    {
        $this->client = $clientManager->getClient();
        $this->sailthruSettings = $sailthruSettings;
    }

    /**
     * Saving customer subscription status
     *
     * @param generic Subscriber Model $subscriberModel
     * @param loaded Subscriber $subscriber
     * @return  $subscriber
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */


    public function afterSave(Subscriber $subscriberModel, $subscriber)
    {
        $this->updateSailthruSubscription($subscriber);
        return $subscriber;
    }

    /**
     * Saving customer unsubscribe status through FrontEnd Control Panel
     *
     * @param generic Subscriber Model $subscriberModel
     * @param loaded Subscriber $subscriber
     * @return  $subscriber
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function afterUnsubscribeCustomerById(Subscriber $subscriberModel, Subscriber $subscriber)
    {
        $this->updateSailthruSubscription($subscriber);
        return $subscriber;
    }

    public function updateSailthruSubscription(Subscriber $subscriber)
    {
        $email = $subscriber->getEmail();
        $status = $subscriber->getStatus();
        $isSubscribed = ($status == Subscriber::STATUS_SUBSCRIBED ? 1 : 0);

        if (($status == Subscriber::STATUS_UNSUBSCRIBED or $status == Subscriber::STATUS_SUBSCRIBED)
            and $this->sailthruSettings->newsletterListEnabled()) {

            $data = [
                    'id'     => $email,
                    'key'    => 'email',
                    'lists'  => [ $this->sailthruSettings->getNewsletterList() => $isSubscribed ],
            ];
            if ($fullName = $subscriber->getSubscriberFullName()) {
                $data['vars'] = [
                    'firstName' => $subscriber->getFirstname(),
                    'lastName'  => $subscriber->getLastname(),
                    'name'      => $fullName,
                ];
            }
            try {
                $this->client->_eventType = $isSubscribed ? 'CustomerSubscribe' : 'CustomerUnsubscribe';
                $this->client->apiPost('user', $data);
            } catch(\Sailthru_Client_Exception $e) {
                $this->client->logger($e->getMessage());
                throw new \Exception($e->getMessage());
            }
        }
    }
}
