<?php

namespace App\Controller\Admin;

use App\Entity\Recipe;
use App\Form\RecipeType;
use App\Repository\RecipeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route("/admin/recettes", name: 'admin.recipe.')]
#[IsGranted('ROLE_ADMIN')]
final class RecipeController extends AbstractController
{
    #[Route('/', name: 'index', methods: ["GET"])]
    public function index(Request $request, RecipeRepository $repository): Response
    {
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

        $page = $request->query->getInt('page', 1);
        $recipes = $repository->paginateRecipes(max($page, 1));

        return $this->render('admin/recipe/index.html.twig', [
            'recipes' => $recipes,
        ]);
    }

    #[Route("/create", name: "create", methods: ["GET", "POST"])]
    public function create(Request $request, EntityManagerInterface $em): Response
    {
        $recipe = new Recipe();
        $form = $this->createForm(RecipeType::class, $recipe);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($recipe);
            $em->flush();
            $this->addFlash("success", "La recette a bien été créée");
            return $this->redirectToRoute("admin.recipe.index");
        }

        return $this->render("admin/recipe/create.html.twig", [
            "form" => $form,
        ]);
    }

    #[Route("/{id}", name: "edit", methods: ["GET", "POST"], requirements: ['id' => Requirement::DIGITS])]
    public function edit(Recipe $recipe, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(RecipeType::class, $recipe);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash("success", "La recette a bien été modifiée");
            return $this->redirectToRoute('admin.recipe.index');
        }

        return $this->render("admin/recipe/edit.html.twig", [
            'recipe' => $recipe,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: "delete", methods: ["DELETE"], requirements: ['id' => Requirement::DIGITS])]
    public function delete(Recipe $recipe, EntityManagerInterface $em): Response
    {
        $em->remove($recipe);
        $em->flush();
        $this->addFlash("success", "La recette a bien été supprimée");
        return $this->redirectToRoute("admin.recipe.index");
    }
}
