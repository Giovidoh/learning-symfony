<?php

namespace App\Controller;

use App\Entity\Recipe;
use App\Form\RecipeType;
use App\Repository\RecipeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class RecipeController extends AbstractController
{
    #[Route('/recettes', name: 'recipe.index')]
    public function index(Request $request, RecipeRepository $repository, EntityManagerInterface $em): Response
    {
        $recipes = $repository->findWithDurationLowerThan(20);

        // Ajout
        // $recipe = new Recipe();
        // $recipe->setTitle('aboda')
        //     ->setSlug('aboda')
        //     ->setContent('Graines de Maïs préparées...')
        //     ->setDuration(15)
        //     ->setCreatedAt(new \DateTimeImmutable())
        //     ->setUpdatedAt(new \DateTimeImmutable());
        // $em->persist($recipe);
        // $em->flush();

        // Modification
        // $recipes[0]->setTitle('test');
        // $em->flush();

        // Suppression
        // $em->remove($recipes[0]);
        // $em->flush();
        //// Il faut récupérer la liste des recettes à nouveau
        //// pour avoir la liste épurée de la recette supprimée
        // $recipes = $recipeRepository->findWithDurationLowerThan(20);

        return $this->render('recipe/index.html.twig', [
            'recipes' => $recipes,
        ]);
    }

    #[Route('/recettes/{slug}-{id}', name: 'recipe.show', requirements: ['id' => '\d+', 'slug' => '[a-z0-9-]+'])]
    public function show(Request $request, string $slug, int $id, RecipeRepository $recipeRepository): Response
    {
        $recipe = $recipeRepository->find($id);
        if ($recipe->getSlug() !== $slug) {
            return $this->redirectToRoute('recipe.show', ['slug' => $recipe->getSlug(), 'id' => $id]);
        }

        return $this->render("recipe/show.html.twig", [
            "recipe" => $recipe,
        ]);
    }

    #[Route("/recettes/create", name: "recipe.create")]
    public function create(Request $request, EntityManagerInterface $em): Response
    {
        $recipe = new Recipe();
        $form = $this->createForm(RecipeType::class, $recipe);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($recipe);
            $em->flush();
            $this->addFlash("success", "La recette a bien été créée");
            return $this->redirectToRoute("recipe.index");
        }

        return $this->render("recipe/create.html.twig", [
            "form" => $form,
        ]);
    }

    #[Route("/recettes/{id}/edit", name: "recipe.edit", methods: ["GET", "POST"])]
    public function edit(Recipe $recipe, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(RecipeType::class, $recipe);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash("success", "La recette a bien été modifiée");
            return $this->redirectToRoute('recipe.index');
        }

        return $this->render("recipe/edit.html.twig", [
            'recipe' => $recipe,
            'form' => $form,
        ]);
    }

    #[Route('/recettes/{id}/delete', name: "recipe.delete", methods: ["DELETE"])]
    public function delete(Recipe $recipe, Request $request, EntityManagerInterface $em): Response
    {
        $em->remove($recipe);
        $em->flush();
        $this->addFlash("success", "La recette a bien été supprimée");
        return $this->redirectToRoute("recipe.index");
    }
}
