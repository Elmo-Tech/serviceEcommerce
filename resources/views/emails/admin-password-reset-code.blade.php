<p>{{ __('mail.password_reset_code_intro') }}</p>

<p><strong>{{ $code }}</strong></p>

<p>{{ __('mail.password_reset_code_expiry', ['minutes' => $expiresInMinutes]) }}</p>

<p>{{ __('mail.password_reset_code_ignore') }}</p>
