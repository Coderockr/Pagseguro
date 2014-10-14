<?php

namespace Coderockr\Pagseguro;

interface CouponInterface
{
    /**
     * @return int
     */
    public function getId();

    /**
     * @return string
     */
    public function getName();

    /**
     * @return string
     */
    public function getCode();

    /**
     * @return float
     */
    public function getValue();

    /**
     * @return boolean
     */
    public function isValid();
}