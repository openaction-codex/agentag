<?php

namespace App\Controller;

use App\AgentTag\Mattermost\MattermostInteractionHandler;
use App\AgentTag\Mattermost\MattermostPayloadParser;
use App\AgentTag\Mattermost\MattermostWebhookAuthenticator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class MattermostWebhookController extends AbstractController
{
    #[Route('/integrations/mattermost/webhook', name: 'mattermost_webhook', methods: ['POST'])]
    public function __invoke(
        Request $request,
        MattermostPayloadParser $payloadParser,
        MattermostWebhookAuthenticator $authenticator,
        MattermostInteractionHandler $interactionHandler,
    ): Response {
        try {
            $event = $payloadParser->parse($request);
        } catch (\InvalidArgumentException $exception) {
            return $this->json(['error' => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        if (!$authenticator->isAllowed($event)) {
            return $this->json(['error' => 'Mattermost webhook token is invalid.'], Response::HTTP_FORBIDDEN);
        }

        $result = $interactionHandler->handle($event);
        if (!$result->isHandled()) {
            return new Response('', Response::HTTP_NO_CONTENT);
        }
        if ('' === $result->message()) {
            return new Response('', Response::HTTP_NO_CONTENT);
        }

        return new JsonResponse([
            'response_type' => 'comment',
            'text' => $result->message(),
        ]);
    }
}
