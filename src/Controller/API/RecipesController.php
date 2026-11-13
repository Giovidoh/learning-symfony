<?php

namespace App\Controller\API;

use App\DTO\PaginationDTO;
use App\Entity\Recipe;
use App\Repository\RecipeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class RecipesController extends AbstractController
{
    #[Route("/api/recipes", methods: ["GET"])]
    #[IsGranted("ROLE_USER")]
    public function index(
        RecipeRepository $repository,
        #[MapQueryString()]
        PaginationDTO $paginationDTO
    ) {
        $recipes = $repository->paginateRecipes($paginationDTO?->page);

        // dd($serializer->serialize($recipes, 'yaml', [
        //     'groups' => ['recipes.index']
        // ]));

        return $this->json($recipes, 200, [], [
            'groups' => ['recipes.index']
        ]);
    }

    #[Route("/api/recipes/{id}", requirements: ['id' => Requirement::DIGITS])]
    #[IsGranted("ROLE_USER")]
    public function show(Recipe $recipe)
    {
        return $this->json($recipe, 200, [], [
            'groups' => ['recipes.index', 'recipes.show']
        ]);
    }

    #[Route("/api/recipes", methods: ['POST'])]
    #[IsGranted("ROLE_USER")]
    public function create(
        #[MapRequestPayload(
            serializationContext: [
                'groups' => ['recipes.create']
            ]
        )]
        Recipe $recipe,
        EntityManagerInterface $em,
    ) {
        $recipe->setCreatedAt(new \DateTimeImmutable());
        $recipe->setUpdatedAt(new \DateTimeImmutable());
        $em->persist($recipe);
        $em->flush();

        return $this->json($recipe, 200, [], [
            'groups' => ['recipes.index', 'recipes.show']
        ]);
    }
}
