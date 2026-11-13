<?php

namespace App\Controller\API;

use App\Entity\Recipe;
use App\Repository\RecipeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Serializer\SerializerInterface;

final class RecipesController extends AbstractController
{
    #[Route("/api/recipes")]
    public function index(Request $request, RecipeRepository $repository, SerializerInterface $serializer)
    {
        $page = $request->query->getInt("page");
        $recipes = $repository->paginateRecipes(max($page, 1));

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
}
