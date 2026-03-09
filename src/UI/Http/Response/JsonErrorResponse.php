<?php

namespace App\UI\Http\Response;

use Symfony\Component\HttpFoundation\JsonResponse;

final class JsonErrorResponse
{
    private const ERROR_MESSAGES = [
        // Auth
        'unauthorized' => 'Authentication required. Please log in.',
        'forbidden' => 'You do not have permission to access this resource.',
        
        // Resources
        'not_found' => 'The requested resource was not found.',
        
        // JSON
        'invalid_json' => 'The request body contains invalid JSON.',
        
        // Groups
        'name_required' => 'The group name is required and cannot be empty.',
        
        // Activities
        'activity_not_in_group' => 'The activity does not belong to this group.',
        
        // Sessions
        'activity_id_required' => 'The activityId field is required.',
        'played_at_required' => 'The playedAt field is required.',
        'invalid_played_at' => 'The playedAt value is not a valid ISO 8601 date.',
        'session_not_in_group' => 'The session does not belong to this group.',
        
        // Entries Score Simple
        'scores_required' => 'The scores field is required.',
        'scores_empty' => 'The scores array cannot be empty.',
        'scores_must_be_array' => 'The scores field must be an array.',
        'score_must_be_object' => 'Each score must be an object with participantName and score.',
        'participant_name_required' => 'The participantName field is required for each score.',
        'score_value_required' => 'The score value is required for each score.',
        'score_must_be_numeric' => 'The score value must be numeric.',
        'participant_user_id_invalid' => 'The score userId value must be a positive integer.',
        'participant_user_not_found' => 'The score user was not found.',
        'participant_user_not_in_group' => 'The score user does not belong to this group.',
        
        // Entries Match
        'home_name_required' => 'The homeName field is required and cannot be empty.',
        'away_name_required' => 'The awayName field is required and cannot be empty.',
        'teams_must_be_different' => 'The home and away team names must be different.',
        'home_score_required' => 'The homeScore field is required.',
        'away_score_required' => 'The awayScore field is required.',
        'home_score_must_be_numeric' => 'The homeScore value must be numeric.',
        'away_score_must_be_numeric' => 'The awayScore value must be numeric.',
        'home_score_must_be_positive' => 'The homeScore value must be greater than or equal to 0.',
        'away_score_must_be_positive' => 'The awayScore value must be greater than or equal to 0.',
        'home_user_id_invalid' => 'The homeUserId value must be a positive integer.',
        'away_user_id_invalid' => 'The awayUserId value must be a positive integer.',
        'home_user_not_found' => 'The home user was not found.',
        'away_user_not_found' => 'The away user was not found.',
        'home_user_not_in_group' => 'The home user does not belong to this group.',
        'away_user_not_in_group' => 'The away user does not belong to this group.',
        'match_users_must_be_different' => 'The home and away users must be different.',
        
        // Generic
        'invalid_request' => 'The request contains invalid data.',
        'internal_error' => 'An internal error occurred. Please try again later.',
    ];

    public static function create(string $errorCode, int $statusCode, ?string $message = null): JsonResponse
    {
        $responseMessage = $message ?? self::ERROR_MESSAGES[$errorCode] ?? 'An error occurred.';

        return new JsonResponse([
            'error' => [
                'code' => $errorCode,
                'message' => $responseMessage,
            ],
        ], $statusCode);
    }
}
