<?php

namespace App\Mail;

class SupplierProductMappingCreatedMail extends BaseSystemMail
{
    protected string $templateName = 'supplier-product-mapping-created';

    protected function getSubject(): string
    {
        return "New Product-Supplier Mapping Created - {$this->emailData['mapping']['product_name']}";
    }
}
