<?php

namespace Coderockr\Pagseguro;

interface TransactionInterface
{
    /**
     * @return int
     */
    public function getId();

    /**
     * @return Buyer
     */
    public function getBuyer();

    /**
     * @return Item
     */
    public function getItem();

    /**
     * Pagseguro transaction code
     * @return string
     */
    public function getCode();

    /**
     * @return TransactionStatus
     */
    public function getStatus();

    /**
     * @return Coupon
     */
    public function getCoupon();

    /**
     * Transaction final value
     * @return float
     */
    public function getValue();

    /**
     * Save a new transaction
     * @return boolean
     */
    public function save();
}