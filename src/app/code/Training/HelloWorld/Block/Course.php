<?php
namespace Training\HelloWorld\Block;

use Magento\Framework\View\Element\Template;

class Course extends Template
{
    public function getTitle()
    {
        return "Danh sách khóa học Magento 2";
    }

    public function getItems()
    {
        return ['PHP Backend', 'Magento Template', 'KnockoutJS', 'API Rest'];
    }
}
