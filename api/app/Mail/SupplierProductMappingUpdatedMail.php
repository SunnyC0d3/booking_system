<?php

namespace App\Mail;

class SupplierProductMappingUpdatedMail extends BaseSystemMail
{
    protected string $templateName = 'supplier-product-mapping-updated';

    protected function getSubject(): string
    {
        return "Product-Supplier Mapping Updated - {$this->emailData['mapping']['product_name']}";
    }
}
