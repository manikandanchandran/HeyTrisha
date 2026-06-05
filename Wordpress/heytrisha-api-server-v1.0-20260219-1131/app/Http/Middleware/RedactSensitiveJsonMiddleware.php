<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedactSensitiveJsonMiddleware
{
    /**
     * @param Request $request
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $contentType = $response->headers->get('Content-Type', '');
        if (stripos($contentType, 'application/json') === false) {
            return $response;
        }

        $raw = $response->getContent();
        if (!is_string($raw) || $raw === '') {
            return $response;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return $response;
        }

        $sanitized = $this->redact($decoded);
        $response->setContent(json_encode($sanitized));

        return $response;
    }

    /**
     * @param mixed $data
     * @return mixed
     */
    private function redact($data)
    {
        $sensitiveKeyPattern = '/(password|pass|pwd|secret|token|api[_-]?key|consumer[_-]?secret|authorization|auth|session|cookie|card|cvc|cvv|pan|user_pass|pm_)/i';
        $sensitiveValuePatterns = [
            '/\bsk-[A-Za-z0-9]{10,}\b/',
            '/\bpk_(live|test)_[A-Za-z0-9]{10,}\b/',
            '/\bsk_(live|test)_[A-Za-z0-9]{10,}\b/',
            '/\bwhsec_[A-Za-z0-9]{10,}\b/',
            '/\b(pm|pi|tok)_[A-Za-z0-9]{10,}\b/',
            '/\bbase64:[A-Za-z0-9+\/]{20,}={0,2}\b/',
        ];

        if (is_array($data)) {
            $out = [];
            foreach ($data as $k => $v) {
                if (is_string($k) && preg_match($sensitiveKeyPattern, $k)) {
                    continue;
                }
                $out[$k] = $this->redact($v);
            }
            return $out;
        }

        if (is_string($data)) {
            foreach ($sensitiveValuePatterns as $p) {
                if (preg_match($p, $data)) {
                    return '[REDACTED]';
                }
            }
        }

        return $data;
    }
}

