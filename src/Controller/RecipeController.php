<?php

namespace App\Controller;

use App\Entity\Recipe;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * Page publique d'une recette : c'est elle que Gotenberg convertit en PDF.
 */
final class RecipeController extends AbstractController
{
    #[Route('/recettes/{id}', name: 'recipe.show', methods: ['GET'], requirements: ['id' => Requirement::DIGITS])]
    public function show(Recipe $recipe): Response
    {
        return $this->render('recipe/show.html.twig', [
            'recipe' => $recipe,
        ]);
    }
}
