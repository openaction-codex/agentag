<?php

namespace App\Controller;

use App\AgentTag\Slack\SlackInteractionHandler;
use App\AgentTag\Slack\SlackPayloadParser;
use App\AgentTag\Slack\SlackSettings;
use App\AgentTag\Slack\SlackWebhookAuthenticator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SlackEventsController extends AbstractController
{
    #[Route('/integrations/slack/events', name: 'slack_events', methods: ['POST'])]
    public function __invoke(
        Request $request,
        SlackSettings $settings,
        SlackPayloadParser $payloadParser,
        SlackWebhookAuthenticator $authenticator,
        SlackInteractionHandler $interactionHandler,
    ): Response {
        if (!$settings->enabled()) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }

        try {
            $payload = $payloadParser->payload($request);
            if ('url_verification' === ($payload['type'] ?? null)) {
                return $this->urlVerificationResponse($payload, $authenticator);
            }

            $event = $payloadParser->parseEvent($payload);
        } catch (\InvalidArgumentException $exception) {
            return $this->json(['error' => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        if (!$authenticator->isAllowed($event)) {
            return $this->json(['error' => 'Slack verification token is invalid.'], Response::HTTP_FORBIDDEN);
        }

        $result = $interactionHandler->handle($event);

        return new JsonResponse([
            'ok' => true,
            'handled' => $result->isHandled(),
            'text' => $result->message(),
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function urlVerificationResponse(array $payload, SlackWebhookAuthenticator $authenticator): Response
    {
        if (!$authenticator->isUrlVerificationAllowed($payload)) {
            return $this->json(['error' => 'Slack verification token is invalid.'], Response::HTTP_FORBIDDEN);
        }

        $challenge = $payload['challenge'] ?? null;
        if (!is_string($challenge) || '' === $challenge) {
            return $this->json(['error' => 'Slack URL verification payload is missing "challenge".'], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse(['challenge' => $challenge]);
    }
}
