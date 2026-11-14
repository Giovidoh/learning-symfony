<?php

namespace App\Controller\Admin;

use App\DTO\PaginationDTO;
use App\Entity\Recipe;
use App\Entity\User;
use App\Form\RecipeType;
use App\Repository\RecipeRepository;
use App\Security\Voter\RecipeVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\UX\Turbo\TurboBundle;

#[Route("/admin/recettes", name: 'admin.recipe.')]
// #[IsGranted('ROLE_ADMIN')]
final class RecipeController extends AbstractController
{
    #[Route('/', name: 'index', methods: ["GET"])]
    #[IsGranted(RecipeVoter::LIST)]
    public function index(
        RecipeRepository $repository,
        #[MapQueryString()]
        PaginationDTO $paginationDTO,
        Security $security
    ): Response {
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

        $user = $security->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $userId = $user->getId();
        $canListAll = $security->isGranted(RecipeVoter::LIST_ALL);
        $recipes = $repository->paginateRecipes($paginationDTO->page, $canListAll ? null : $userId);

        return $this->render('admin/recipe/index.html.twig', [
            'recipes' => $recipes,
        ]);
    }

    #[Route("/create", name: "create", methods: ["GET", "POST"])]
    #[IsGranted(RecipeVoter::CREATE)]
    public function create(Request $request, EntityManagerInterface $em): Response
    {
        $recipe = new Recipe();
        $form = $this->createForm(RecipeType::class, $recipe);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $recipe->setUser($this->getUser());
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
    #[IsGranted(RecipeVoter::EDIT, subject: 'recipe')]
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
    #[IsGranted(RecipeVoter::EDIT, subject: 'recipe')]
    public function delete(Request $request, Recipe $recipe, EntityManagerInterface $em): Response
    {
        $recipeId = $recipe->getId();
        $message = "La recette a bien été supprimée";
        $em->remove($recipe);
        $em->flush();
        if ($request->getPreferredFormat() == TurboBundle::STREAM_FORMAT) {
            $request->setRequestFormat(TurboBundle::STREAM_FORMAT);
            return $this->render('admin/recipe/delete.html.twig', ['recipeId' => $recipeId, 'message' => $message]);
        }
        $this->addFlash("success", $message);
        return $this->redirectToRoute("admin.recipe.index");
    }
}
