# Daraja API setup for POS

## 1) Environment variables
Add these values to your server environment or a local .env file:

- DARAJA_BASE_URL=https://sandbox.safaricom.co.ke
- DARAJA_CONSUMER_KEY=your_consumer_key
- DARAJA_CONSUMER_SECRET=your_consumer_secret
- DARAJA_SHORTCODE=your_paybill_shortcode
- DARAJA_PASSKEY=your_passkey
- DARAJA_INITIATOR_NAME=your_initiator_name
- DARAJA_SECURITY_CREDENTIAL=your_security_credential
- DARAJA_B2B_SHORTCODE=your_b2b_shortcode
- DARAJA_B2B_TYPE=BusinessBuyGoods
- DARAJA_CALLBACK_URL=https://your-domain.example.com/api/daraja/callback

## 2) What is now available
- Phone-number normalization and validation for Kenyan Mpesa numbers
- STK push request support for customer payments
- B2B payment request support for business-to-business payments
- A POS UI entry point in sales.php to validate a phone number and trigger the requests

## 3) How to use it
1. Open the Sales page.
2. Enter a phone number in the Mpesa field.
3. Click Validate Phone to verify the number format.
4. Click STK Push to initiate a customer payment request.
5. Click B2B Request to submit a B2B transfer request.

## 4) Notes
- The sandbox endpoints require test credentials and a sandbox phone number.
- Production use should be tested carefully with your Daraja account and callback handling.
- The new endpoint is protected with the existing CSRF validation.
