<?php

namespace App\Controller\API;

use App\DTO\PaginationDTO;
use App\Entity\Recipe;
use App\Entity\User;
use App\Repository\RecipeRepository;
use App\Security\Voter\RecipeVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted("ROLE_USER")]
final class RecipesController extends AbstractController
{
    #[Route("/api/recipes", methods: ["GET"])]

    public function index(
        RecipeRepository $repository,
        #[MapQueryString()]
        PaginationDTO $paginationDTO,
        Security $security
    ) {
        $user = $security->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $userId = $user->getId();
        $canListAll = $security->isGranted(RecipeVoter::LIST_ALL);
        $recipes = $repository->paginateRecipes($paginationDTO->page, $canListAll ? null : $userId);

        // dd($serializer->serialize($recipes, 'yaml', [
        //     'groups' => ['recipes.index']
        // ]));

        return $this->json($recipes, 200, [], [
            'groups' => ['recipes.index']
        ]);
    }

    #[Route("/api/recipes/{id}", requirements: ['id' => Requirement::DIGITS])]
    public function show(Recipe $recipe)
    {
        return $this->json($recipe, 200, [], [
            'groups' => ['recipes.index', 'recipes.show']
        ]);
    }

    #[Route("/api/recipes", methods: ['POST'])]
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
        $recipe->setUser($this->getUser());
        $em->persist($recipe);
        $em->flush();

        return $this->json($recipe, 200, [], [
            'groups' => ['recipes.index', 'recipes.show']
        ]);
    }
}
