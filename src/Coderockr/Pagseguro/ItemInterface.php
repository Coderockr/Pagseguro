<?php

namespace Coderockr\Pagseguro;

interface ItemInterface
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
    public function getDescription();

    /**
     * @return float
     */
    public function getValue();

    /**
     * @return string
     */
    public function getRedirectUrl();
}