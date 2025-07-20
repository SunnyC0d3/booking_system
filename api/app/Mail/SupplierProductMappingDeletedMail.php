<?php

namespace App\Mail;

class SupplierProductMappingDeletedMail extends BaseSystemMail
{
    protected string $templateName = 'supplier-product-mapping-deleted';

    protected function getSubject(): string
    {
        return "Product-Supplier Mapping Deleted - {$this->emailData['mapping']['product_name']}";
    }
}
