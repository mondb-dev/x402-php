<?php

declare(strict_types=1);

namespace X402\Exceptions;

/**
 * Standardized error codes for x402 protocol.
 * 
 * These match the error codes used in the official x402 implementations.
 */
class ErrorCodes
{
    // General errors
    public const INVALID_VERSION = 'invalid_version';
    public const INVALID_SCHEME = 'invalid_scheme';
    public const INVALID_NETWORK = 'invalid_network';
    
    // EVM exact scheme errors
    public const INVALID_EVM_SIGNATURE = 'invalid_exact_evm_payload_signature';
    public const INVALID_EVM_RECIPIENT = 'invalid_exact_evm_payload_recipient_mismatch';
    public const INVALID_EVM_VALUE = 'invalid_exact_evm_payload_authorization_value';
    public const INVALID_EVM_VALID_AFTER = 'invalid_exact_evm_payload_authorization_valid_after';
    public const INVALID_EVM_VALID_BEFORE = 'invalid_exact_evm_payload_authorization_valid_before';
    
    // SVM (Solana) exact scheme errors
    public const INVALID_SVM_TRANSACTION = 'invalid_exact_svm_payload_transaction';
    public const INVALID_SVM_AMOUNT_MISMATCH = 'invalid_exact_svm_payload_transaction_amount_mismatch';
    public const INVALID_SVM_RECIPIENT = 'invalid_exact_svm_payload_transaction_transfer_to_incorrect_ata';
    public const INVALID_SVM_INSTRUCTION = 'invalid_exact_svm_payload_transaction_instruction_not_spl_token_transfer_checked';
    public const INVALID_SVM_INSTRUCTIONS_LENGTH = 'invalid_exact_svm_payload_transaction_instructions_length';
    public const INVALID_SVM_CREATE_ATA = 'invalid_exact_svm_payload_transaction_create_ata_instruction';
    public const INVALID_SVM_SENDER_ATA = 'invalid_exact_svm_payload_transaction_sender_ata_not_found';
    public const INVALID_SVM_RECEIVER_ATA = 'invalid_exact_svm_payload_transaction_receiver_ata_not_found';
    
    // Payment errors
    public const INSUFFICIENT_FUNDS = 'insufficient_funds';
    public const PAYMENT_REQUIRED = 'payment_required';
    
    // Facilitator errors
    public const FACILITATOR_ERROR = 'facilitator_error';
    public const FACILITATOR_VERIFICATION_FAILED = 'facilitator_verification_failed';
    public const FACILITATOR_REQUIRED = 'facilitator_required';
    
    // Transaction errors
    public const INVALID_TRANSACTION_STATE = 'invalid_transaction_state';
    
    // Rate limiting
    public const RATE_LIMIT_EXCEEDED = 'rate_limit_exceeded';
    
    // Compliance
    public const COMPLIANCE_CHECK_FAILED = 'compliance_check_failed';
    public const ADDRESS_BLOCKED = 'address_blocked';
    
    // Nonce/Replay protection
    public const NONCE_ALREADY_USED = 'nonce_already_used';
    public const INVALID_NONCE = 'invalid_nonce';
    
    // EIP-712 Domain
    public const INVALID_EIP712_DOMAIN = 'invalid_eip712_domain';
}
