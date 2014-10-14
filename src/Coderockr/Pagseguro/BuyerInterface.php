<?php

namespace Coderockr\Pagseguro;

interface BuyerInterface
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
    public function getEmail();    
}