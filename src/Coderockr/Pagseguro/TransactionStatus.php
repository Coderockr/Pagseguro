<?php
namespace Coderockr\Pagseguro;

class TransactionStatus
{
    const AWAITING = 1;
    const ANALYSIS = 2;
    const PAID = 3;
    const AVAILABLE = 4;
    const CONTESTED = 5;
    const RETURNED = 6;
    const CANCELED = 7;

    public function getDescription($status)
    {
        switch ($status) {
            case $this::AWAITING:
                return 'Aguardando';
                break;
            case $this::ANALYSIS:
                return 'Em análise';
                break;
            case $this::PAID:
                return 'Pago';
                break;
            case $this::AVAILABLE:
                return 'Disponível';
                break;
            case $this::CONTESTED:
                return 'Contestado';
                break;
            case $this::RETURNED:
                return 'Retornado';
                break;
            case $this::CANCELED:
                return 'Cancelado';
                break;
            default:
                return 'Indefinido';
                break;
        }
    }
}
