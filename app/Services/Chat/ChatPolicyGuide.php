<?php

namespace App\Services\Chat;

/**
 * Short guidance taken from the published returns, warranty and shipping pages.
 * Requests are collected for staff. Nothing here approves a return, refund or claim.
 */
class ChatPolicyGuide
{
    public function returnsSummary(): string
    {
        return 'Defective, damaged or incorrectly supplied items may be returned within 14 calendar days of delivery. '
            .'The product must be unused and in original packaging with all accessories. '
            .'Proof of purchase (order number or invoice) is required. '
            .'Software licences, digital products and opened consumables are generally not returnable. '
            .'Custom-built or special-order items may not be eligible for return. '
            .'Our team confirms eligibility and provides return instructions. '
            .'Submitting a request here does not approve a return or a refund. '
            .'Read the returns policy: '.route('returns');
    }

    public function warrantySummary(): string
    {
        return 'Most hardware carries a manufacturer warranty. Warranty periods vary by brand and product and are shown on the product page or your invoice where available. '
            .'Coverage is for manufacturing defects in materials and workmanship and for hardware failure under normal use during the warranty period. '
            .'Warranty does not cover physical damage, liquid damage, unauthorised modifications, misuse, or normal wear and tear. '
            .'Products that are faulty on arrival must be reported within 14 days of delivery. '
            .'Do not send items without a return authorisation reference. '
            .'Submitting a request here does not approve a warranty claim. '
            .'Read the warranty terms: '.route('warranty');
    }

    public function supportSummary(): string
    {
        $phone = (string) config('business.phone');
        $email = (string) config('business.email');

        return 'Send the details and a person on the Urban Focus team will read them during published hours. '
            .'You can also call '.$phone.' or email '.$email.'. '
            .'This chat cannot approve a refund, a return or a warranty claim. '
            .'Contact page: '.route('contact');
    }
}
