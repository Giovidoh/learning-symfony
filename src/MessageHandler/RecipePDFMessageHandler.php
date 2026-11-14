<?php

namespace App\MessageHandler;

use App\Message\RecipePDFMessage;
use App\Repository\RecipeRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsMessageHandler]
final class RecipePDFMessageHandler
{
    public function __construct(
        #[Autowire('%kernel.project_dir%/public/pdfs')]
        private readonly string $path,
        #[Autowire('%app.gotenberg_endpoint%')]
        private readonly string $gotenbergEndpoint,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly HttpClientInterface $httpClient,
        private readonly RecipeRepository $recipeRepository,
        private readonly Filesystem $filesystem,
    ) {}

    public function __invoke(RecipePDFMessage $message): void
    {
        // La recette a pu être supprimée entre le dispatch et le traitement : rien à faire
        if (null === $this->recipeRepository->find($message->id)) {
            return;
        }

        // Gotenberg attend du multipart/form-data
        $form = new FormDataPart([
            'url' => $this->urlGenerator->generate('recipe.show', ['id' => $message->id], UrlGeneratorInterface::ABSOLUTE_URL),
        ]);

        $response = $this->httpClient->request('POST', $this->gotenbergEndpoint . '/forms/chromium/convert/url', [
            'headers' => $form->getPreparedHeaders()->toArray(),
            'body' => $form->bodyToIterable(),
        ]);

        // getContent() lève une exception si le code HTTP n'est pas 2xx → Messenger réessaie
        $this->filesystem->dumpFile(\sprintf('%s/%d.pdf', $this->path, $message->id), $response->getContent());
    }
}
