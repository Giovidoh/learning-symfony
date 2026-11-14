<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

final class HomeController extends AbstractController
{
    // #[Route("/{_locale}", name: "home.locale")]
    #[Route("/", name: "home")]
    public function index(TranslatorInterface $translator): Response
    {
        // dd($translator->trans('Welcome'));
        return $this->render("home/index.html.twig");
    }
}
