param(
    [switch] $ExerciseThrottle
)

$ErrorActionPreference = 'Stop'

$baseUrl = $env:ADMIN_AUTH_SMOKE_BASE_URL
$email = $env:ADMIN_AUTH_SMOKE_EMAIL
$password = $env:ADMIN_AUTH_SMOKE_PASSWORD
$origin = $env:ADMIN_AUTH_SMOKE_ORIGIN
$mailFailureEmail = $env:ADMIN_AUTH_SMOKE_MAIL_FAILURE_EMAIL

if ([string]::IsNullOrWhiteSpace($baseUrl) -or [string]::IsNullOrWhiteSpace($email) -or [string]::IsNullOrWhiteSpace($password)) {
    throw 'Set ADMIN_AUTH_SMOKE_BASE_URL, ADMIN_AUTH_SMOKE_EMAIL, and ADMIN_AUTH_SMOKE_PASSWORD before running this smoke script.'
}

$baseUrl = $baseUrl.TrimEnd('/')

function Invoke-AdminAuthJson {
    param(
        [Parameter(Mandatory = $true)] [string] $Method,
        [Parameter(Mandatory = $true)] [string] $Path,
        [object] $Body = $null,
        [string] $BearerToken = '',
        [int[]] $ExpectedStatus = @(200)
    )

    $headers = @{
        Accept = 'application/json'
        'Accept-Language' = 'en'
    }

    if (-not [string]::IsNullOrWhiteSpace($origin)) {
        $headers['Origin'] = $origin
    }

    if (-not [string]::IsNullOrWhiteSpace($BearerToken)) {
        $headers['Authorization'] = "Bearer $BearerToken"
    }

    $request = @{
        Method = $Method
        Uri = "$baseUrl$Path"
        Headers = $headers
    }

    if ($null -ne $Body) {
        $request['ContentType'] = 'application/json'
        $request['Body'] = ($Body | ConvertTo-Json -Depth 10)
    }

    $response = $null
    $responseContent = ''
    $responseHeaders = @{}
    $statusCode = 0

    try {
        $response = Invoke-WebRequest @request
        $statusCode = [int] $response.StatusCode
        $responseContent = [string] $response.Content

        foreach ($key in $response.Headers.Keys) {
            $responseHeaders[$key] = $response.Headers[$key]
        }
    } catch {
        if (-not $_.Exception.Response) {
            throw
        }

        $response = $_.Exception.Response
        $statusCode = [int] $response.StatusCode

        foreach ($key in $response.Headers.AllKeys) {
            $responseHeaders[$key] = $response.Headers[$key]
        }

        $stream = $response.GetResponseStream()
        if ($stream) {
            $reader = [System.IO.StreamReader]::new($stream)
            $responseContent = $reader.ReadToEnd()
            $reader.Dispose()
        }
    }

    if ($ExpectedStatus -notcontains $statusCode) {
        throw "$Method $Path expected $($ExpectedStatus -join ', ') but received $statusCode."
    }

    $content = if (-not [string]::IsNullOrWhiteSpace($responseContent)) {
        $responseContent | ConvertFrom-Json
    } else {
        $null
    }

    [PSCustomObject]@{
        StatusCode = $statusCode
        Headers = $responseHeaders
        Json = $content
    }
}

function Assert-HeaderSet {
    param(
        [Parameter(Mandatory = $true)] [object] $Response,
        [Parameter(Mandatory = $true)] [string] $Name
    )

    if (-not $Response.Headers.ContainsKey($Name)) {
        throw "Missing required response header: $Name."
    }
}

function Assert-AuthHeaders {
    param([Parameter(Mandatory = $true)] [object] $Response)

    Assert-HeaderSet $Response 'Content-Language'
    Assert-HeaderSet $Response 'Vary'
    Assert-HeaderSet $Response 'Cache-Control'
    Assert-HeaderSet $Response 'Pragma'

    if ($Response.Headers.ContainsKey('Set-Cookie')) {
        throw 'Authentication smoke failed because a Set-Cookie response header was emitted.'
    }
}

function Assert-ErrorCode {
    param(
        [Parameter(Mandatory = $true)] [object] $Response,
        [Parameter(Mandatory = $true)] [string] $Code
    )

    if ($Response.Json.code -ne $Code) {
        throw "Expected error code $Code but received $($Response.Json.code)."
    }
}

function Assert-SuccessEnvelope {
    param([Parameter(Mandatory = $true)] [object] $Response)

    if ($Response.Json.success -ne $true) {
        throw 'Expected a success envelope.'
    }

    if (-not $Response.Json.PSObject.Properties['data']) {
        throw 'Success envelope is missing data.'
    }

    if ($Response.Json.PSObject.Properties['code']) {
        throw 'Success envelope must not contain code.'
    }
}

$login = Invoke-AdminAuthJson -Method POST -Path '/admin/auth/login' -Body @{
    email = $email
    password = $password
}
Assert-AuthHeaders $login
Assert-SuccessEnvelope $login

$accessTokenA = [string] $login.Json.data.accessToken
$refreshTokenA = [string] $login.Json.data.refreshToken

if ([string]::IsNullOrWhiteSpace($accessTokenA) -or [string]::IsNullOrWhiteSpace($refreshTokenA)) {
    throw 'Login did not return both accessToken and refreshToken.'
}

$profileA = Invoke-AdminAuthJson -Method GET -Path '/admin/auth/profile' -BearerToken $accessTokenA
Assert-AuthHeaders $profileA
Assert-SuccessEnvelope $profileA

$profileKeys = @($profileA.Json.data.PSObject.Properties.Name | Sort-Object)
$expectedProfileKeys = @('avatar', 'email', 'name', 'permissions', 'role')
if (($profileKeys -join '|') -ne (($expectedProfileKeys | Sort-Object) -join '|')) {
    throw "Profile keys were $($profileKeys -join ', '), expected $($expectedProfileKeys -join ', ')."
}

$refresh = Invoke-AdminAuthJson -Method POST -Path '/admin/auth/refresh' -Body @{
    refreshToken = $refreshTokenA
}
Assert-AuthHeaders $refresh
Assert-SuccessEnvelope $refresh

$accessTokenB = [string] $refresh.Json.data.accessToken
$refreshTokenB = [string] $refresh.Json.data.refreshToken

if ([string]::IsNullOrWhiteSpace($accessTokenB) -or [string]::IsNullOrWhiteSpace($refreshTokenB)) {
    throw 'Refresh did not return replacement accessToken and refreshToken.'
}

if ($accessTokenA -eq $accessTokenB -or $refreshTokenA -eq $refreshTokenB) {
    throw 'Refresh did not rotate both token values.'
}

$oldAccess = Invoke-AdminAuthJson -Method GET -Path '/admin/auth/profile' -BearerToken $accessTokenA -ExpectedStatus @(401)
Assert-AuthHeaders $oldAccess
Assert-ErrorCode $oldAccess 'UNAUTHENTICATED'

$reuse = Invoke-AdminAuthJson -Method POST -Path '/admin/auth/refresh' -Body @{
    refreshToken = $refreshTokenA
} -ExpectedStatus @(401)
Assert-AuthHeaders $reuse
Assert-ErrorCode $reuse 'REFRESH_TOKEN_INVALID'

$newAccessAfterReuse = Invoke-AdminAuthJson -Method GET -Path '/admin/auth/profile' -BearerToken $accessTokenB -ExpectedStatus @(401)
Assert-AuthHeaders $newAccessAfterReuse
Assert-ErrorCode $newAccessAfterReuse 'UNAUTHENTICATED'

$forgotUnknown = Invoke-AdminAuthJson -Method POST -Path '/admin/auth/forgot-password' -Body @{
    email = 'unknown-admin@example.test'
}
Assert-AuthHeaders $forgotUnknown
Assert-SuccessEnvelope $forgotUnknown

$forgotValidation = Invoke-AdminAuthJson -Method POST -Path '/admin/auth/forgot-password' -Body @{
    email = 'not-an-email'
} -ExpectedStatus @(422)
Assert-AuthHeaders $forgotValidation
Assert-ErrorCode $forgotValidation 'VALIDATION_ERROR'

$resetValidation = Invoke-AdminAuthJson -Method POST -Path '/admin/auth/reset-password' -Body @{
    email = $email
    resetToken = ''
    password = 'short'
    passwordConfirmation = 'short'
} -ExpectedStatus @(422)
Assert-AuthHeaders $resetValidation
Assert-ErrorCode $resetValidation 'VALIDATION_ERROR'

if (-not [string]::IsNullOrWhiteSpace($mailFailureEmail)) {
    $mailFailure = Invoke-AdminAuthJson -Method POST -Path '/admin/auth/forgot-password' -Body @{
        email = $mailFailureEmail
    } -ExpectedStatus @(503)
    Assert-AuthHeaders $mailFailure
    Assert-ErrorCode $mailFailure 'MAIL_SERVICE_UNAVAILABLE'
}

if ($ExerciseThrottle) {
    foreach ($index in 1..11) {
        $expected = if ($index -eq 11) { @(429, 422) } else { @(422, 429) }
        $invalidRefresh = Invoke-AdminAuthJson -Method POST -Path '/admin/auth/refresh' -Body @{} -ExpectedStatus $expected
        Assert-AuthHeaders $invalidRefresh

        if ($index -eq 11 -and $invalidRefresh.StatusCode -ne 429) {
            throw 'Structurally invalid refresh requests did not hit the IP-only throttle bucket.'
        }
    }

    foreach ($index in 1..11) {
        $randomToken = [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes("smoke-$([Guid]::NewGuid())")).TrimEnd('=').Replace('+', '-').Replace('/', '_')
        $unknownRefresh = Invoke-AdminAuthJson -Method POST -Path '/admin/auth/refresh' -Body @{
            refreshToken = $randomToken
        } -ExpectedStatus @(401, 429)
        Assert-AuthHeaders $unknownRefresh

        if ($unknownRefresh.StatusCode -eq 429) {
            throw 'Structurally valid distinct refresh tokens unexpectedly shared one throttle bucket.'
        }
    }

    foreach ($index in 1..6) {
        $expected = if ($index -eq 6) { @(429, 200) } else { @(200, 429) }
        $forgotThrottle = Invoke-AdminAuthJson -Method POST -Path '/admin/auth/forgot-password' -Body @{
            email = 'forgot-throttle-smoke@example.test'
        } -ExpectedStatus $expected
        Assert-AuthHeaders $forgotThrottle

        if ($index -eq 6 -and $forgotThrottle.StatusCode -ne 429) {
            throw 'Forgot-password smoke did not hit the configured 5/min throttle bucket.'
        }
    }
}

Write-Output 'Admin auth smoke passed.'
