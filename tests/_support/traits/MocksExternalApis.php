<?php

namespace fabian\booked\tests\_support\traits;

/**
 * Trait for mocking external API calls in tests
 */
trait MocksExternalApis
{
    protected function mockGoogleCalendarApi(): void
    {
        // Mock Google Calendar API responses
        // This would use Codeception stubs or similar
    }

    protected function mockOutlookCalendarApi(): void
    {
        // Mock Outlook API responses
    }

    protected function mockZoomApi(): void
    {
        // Mock Zoom API responses
    }

    protected function mockGoogleMeetApi(): void
    {
        // Mock Google Meet API responses
    }

    protected function mockEmailService(): void
    {
        // Mock email sending
    }

    protected function mockSmsService(): void
    {
        // Mock SMS sending
    }
}
