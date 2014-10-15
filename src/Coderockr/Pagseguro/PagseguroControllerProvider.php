<?php

namespace Coderockr\Pagseguro;

use Silex\Application;
use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use SimpleXMLElement;
use Exception;
use Buzz\Client\Curl;
use Buzz\Browser;


class PagseguroControllerProvider implements ControllerProviderInterface
{
    private $token;
    private $email;
    private $buyerClass;
    private $couponClass;
    private $itemClass;
    private $transactionClass;
    private $em;
    private $log;

    public function setEntityManager($em)
    {
        $this->em = $em;
    }
    
    public function getToken()
    {
        return $this->token;
    }
     
    public function setToken($token)
    {
        return $this->token = $token;
    }

    public function getEmail()
    {
        return $this->email;
    }
     
    public function setEmail($email)
    {
        return $this->email = $email;
    }

    public function getBuyerClass()
    {
        return $this->buyerClass;
    }
     
    public function setBuyerClass($buyerClass)
    {
        if (!in_array('Coderockr\Pagseguro\BuyerInterface', class_implements($buyerClass))) {
            throw new Exception("Class must implements BuyerInterface", 1);
        }
        return $this->buyerClass = $buyerClass;
    }

    public function getCouponClass()
    {
        return $this->couponClass;
    }
     
    public function setCouponClass($couponClass)
    {
        if (!in_array('Coderockr\Pagseguro\CouponInterface', class_implements($couponClass))) {
            throw new Exception("Class must implements CouponInterface", 1);
        }
        return $this->couponClass = $couponClass;
    }

    public function getItemClass()
    {
        return $this->itemClass;
    }
     
    public function setItemClass($itemClass)
    {
        if (!in_array('Coderockr\Pagseguro\ItemInterface', class_implements($itemClass))) {
            throw new Exception("Class must implements ItemInterface", 1);
        }
        return $this->itemClass = $itemClass;
    }

    public function getTransactionClass()
    {
        return $this->transactionClass;
    }
     
    public function setTransactionClass($transactionClass)
    {
        if (!in_array('Coderockr\Pagseguro\TransactionInterface', class_implements($transactionClass))) {
            throw new Exception("Class must implements TransactionInterface", 1);
        }
        return $this->transactionClass = $transactionClass;
    }

    public function getLog()
    {
        return $this->log;
    }
     
    public function setLog($log)
    {
        return $this->log = $log;
    }

    public function connect(Application $app)
    {
        $this->setEntityManager($app['orm.em']);
        $controllers = $app['controllers_factory'];

        $controllers->get('/{user}/{item}/{coupon}', function (Application $app, $user, $item, $coupon, Request $request) {
            //get user
            $user = $this->em->getRepository($this->getBuyerClass())->find($user);
            if (!$user) {
                throw new Exception("User not found", 1);
            }
            //get item
            $item = $this->em->getRepository($this->getItemClass())->find($item);
            if (!$item) {
                throw new Exception("Item not found", 1);
            }
            if ($coupon) {
                //get coupon
                $coupon = $this->em->getRepository($this->getCouponClass())->find($coupon);
                if (!$coupon) {
                    throw new Exception("Coupon not found", 1);
                }
            }
            //save a transaction to get the id to use in reference
            $className = $this->getTransactionClass();
            $transaction = new $className;
            $transaction->setBuyer($user);
            $transaction->setItem($item);
            $transaction->setCoupon($coupon);
            $transaction->setStatus(TransactionStatus::AWAITING);
            $this->em->persist($transaction);
            $this->em->flush();

            $parameters = array(
                'email' => $this->getEmail(),
                'token' => $this->getToken(),
                'redirectURL' => $item->getRedirectUrl(),
                'currency' => 'BRL',
                'itemId1' => $item->getId(),
                'itemDescription1' => $item->getName(),
                'itemAmount1' => number_format($item->getValue(),2,'.',''),
                'itemQuantity1' => '1',
                'itemWeight1' => '0',
                'reference' => $transaction->getId()
            );
        if ($coupon) {
            $parameters['extraAmount'] = number_format(-1 * $coupon()->getValue(), 2);
        }

        $client = new Curl();
        $client->setTimeout(30);
        $browser = new Browser($client);
        $response = $browser->post(
            'https://ws.pagseguro.uol.com.br/v2/checkout/',
            array('Content-Type' => 'application/x-www-form-urlencoded; charset=ISO-8859-1'),
            http_build_query($parameters)
        );
        try {
            $responseXML = new SimpleXMLElement($response->getContent());    
        } catch (Exception $e) {
            if ($this->getLog()) {
                $this->getLog()->addError($e->getMessage());
            }
            throw new Exception("Ocorreu um erro na conexão com o servidor do PagSeguro.", 1);
        }
        
        if (count($responseXML->xpath('/errors')) > 0) {
            if ($this->getLog()) {
                $this->getLog()->addError($responseXML->asXml());
            }
            throw new Exception("Ocorreu um erro na conexão com o servidor do PagSeguro: " . $responseXML->asXml(), 1);
        }
            
        $code = array_shift($responseXML->xpath('/checkout/code'));

        return $app->redirect(
            'https://pagseguro.uol.com.br/v2/checkout/payment.html?code=' . $code
        );
        })->value('coupon', null);
        
        //callback
        $controllers->post('/notification', function (Application $app, Request $request) {

            $code = $request->get('notificationCode');
            $type = $request->get('notificationType');

            if ( $code && $type == 'transaction' ) {

                $client = new Curl();
                $client->setTimeout(30);
                $browser = new Browser($client);
                $response = $browser->get(
                    "https://ws.pagseguro.uol.com.br/v2/transactions/notifications/$code?email=" . $this->getEmail() . '&token=' . $this->getToken()
                );

                $xmlContent = $response->getContent();
                $responseXML = new SimpleXMLElement($xmlContent);
                
                $array = array($responseXML);
                $reference = $array[0]->reference;
                // $reference = array_shift($responseXML->xpath('/transaction/reference'));
                if ( ! $reference) {
                    throw new Exception("Invalid response", 1);
                }

                // $description = array_shift($responseXML->xpath('/transaction/items/item/description'));
                $description = $array[0]->items->item->description;
                if ( ! $description) {
                    throw new Exception("Invalid response", 1);
                }

                $transaction = $this->em->getRepository($this->getTransactionClass)->find($reference);

                // $transactionCode = array_shift($responseXML->xpath('/transaction/code'));
                $transactionCode = $array[0]->code;
                $transaction->setCode($transactionCode);

                // $transactionStatus = array_shift($responseXML->xpath('/transaction/status'));
                $transactionStatus = $array[0]->status;
                $transaction->setStatus($transactionStatus);

                // $transactionSenderEmail = array_shift($responseXML
                //         ->xpath('/transaction/sender/email'));
                // $transaction->setEmail((string) $transactionSenderEmail);

                $this->em->persist($transaction);
                $this->em->flush();
            }
            return $app->redirect('/');
        });

        //fim callback
        return $controllers;
    }   
}
