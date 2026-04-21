<?php
namespace Training\HelloWorld\Block;

use Magento\Framework\View\Element\Template;

class TransactionList extends Template {
    public function getTransactions() {
        return [
            ["id" => 1, "desc" => "Mua thẻ nhớ", "amount" => "500.000đ", "date" => "2026-04-20"],
            ["id" => 2, "desc" => "Thanh toán điện", "amount" => "1.200.000đ", "date" => "2026-04-21"],
        ];
    }
}