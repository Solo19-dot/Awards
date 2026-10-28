# Ignite Blantyre Vote

PHP + Bootstrap voting site with Supabase (database) and PayChangu (Airtel Money / Mpamba).
One payment = one ballot = one vote per category.

## 1. Supabase
1. Create a project at supabase.com.
2. Open SQL Editor, paste everything in `sql/schema.sql`, click Run.
3. Project Settings > API: copy the Project URL and the `service_role` key.
   The service_role key is a secret. It only ever lives in `includes/config.php` on your server.

## 2. Configure
1. Copy `includes/config.sample.php` to `includes/config.php`.
2. Fill in `site_url`, `vote_fee`, the Supabase values and the PayChangu keys.
3. Make the admin password hash and paste it into `admin_password_hash`:
   `php -r "echo password_hash('YourStrongPassword', PASSWORD_DEFAULT);"`

## 3. Upload
Upload the whole folder to a host with PHP 8.0+ and the cURL extension.
Make `uploads/nominees/` writable (permissions 755 or 775).
If photo uploads fail, raise `upload_max_filesize` and `post_max_size` in php.ini (2M and 8M are fine).

## 4. PayChangu
1. Dashboard > API & Webhooks: copy your secret key and webhook secret into `config.php`.
2. Add `https://yourdomain.com/webhook.php` as the webhook URL.
3. Test with PayChangu test mode first, then switch to live keys.

## 5. Run the vote
1. Log in at `/admin/` and add categories and nominees under "Categories and nominees".
2. On the Dashboard, switch Voting on.
3. Keep "Public results" off until the dinner. Switch it on to show `/results.php`.

## Notes
- Votes only count after the server verifies the payment with PayChangu.
- Categories or nominees that already have votes cannot be deleted. Hide the category instead.
- Anyone can pay for several ballots. That is how a flat-fee public vote works.
- Check your server error log if a payment does not complete. Failures are logged there.
