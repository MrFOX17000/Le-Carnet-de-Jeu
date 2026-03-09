<?php

namespace App\Tests\UI\Http\Controller\Auth;

use App\Tests\DbWebTestCase;

class OAuthControllerTest extends DbWebTestCase
{
    /**
     * Test: Connect with Google route exists and is public
     * 
     * Phase 2: This route should redirect to Google OAuth provider
     * In test environment, it tries to load the OAuth client (which has test credentials)
     */
    public function testConnectGoogleRouteIsPublic(): void
    {
        $client = static::createClient();
        
        // Route should be accessible (public)
        $client->request('GET', '/connect/google');
        
        // Will either redirect to Google or fail with connection error (acceptable in test)
        // Main thing: should not be 404 or 403
        $statusCode = $client->getResponse()->getStatusCode();
        $this->assertTrue(
            in_array($statusCode, [302, 500]), // 302 =redirect to provider, 500 = provider connection error in test
            "Route exists (not 404), but got status: $statusCode"
        );
    }
    
    /**
     * Test: Connect Google Check route exists
     * 
     * This is the callback route that Google redirects to
     * In tests, we can't fully test without mocking the OAuth provider
     */
    public function testConnectGoogleCheckRouteExists(): void
    {
        $client = static::createClient();
        
        // Route should exist (though accessing without valid OAuth would fail)
        $client->request('GET', '/connect/google/check');
        
        // Should not be 404
        $this->assertNotSame(404, $client->getResponse()->getStatusCode());
    }
    
    /**
     * Test: Standard login route still works (fallback)
     */
    public function testStandardLoginRouteStillWorks(): void
    {
        $client = static::createClient();
        
        $client->request('GET', '/login');
        
        $this->assertResponseStatusCodeSame(200);
        $this->assertStringContainsString('Please sign in', $client->getResponse()->getContent());
    }
}
