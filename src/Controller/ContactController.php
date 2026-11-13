<?php

namespace App\Controller;

use App\DTO\ContactDTO;
use App\Event\ContactRequestEvent;
use App\Form\ContactType;
use Doctrine\ORM\EntityManagerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;

final class ContactController extends AbstractController
{
    #[Route('/contact', name: 'contact.index')]
    public function index(Request $request, MailerInterface $mailer, EventDispatcherInterface $dispatcher): Response
    {
        $data = new ContactDTO();

        $form = $this->createForm(ContactType::class, $data);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $dispatcher->dispatch(new ContactRequestEvent($data));
                $this->addFlash('success', 'Votre email a bien été envoyé');
                return $this->redirectToRoute('contact.index');
            } catch (\Exception $e) {
                $this->addFlash('danger', 'Impossible d\'envoyer votre email');
            }
            // try {
            //     $mail = new TemplatedEmail()
            //         ->to($data->service)
            //         ->from($data->email)
            //         ->subject('Demande de contact')
            //         ->htmlTemplate('emails/contact.html.twig')
            //         ->context(['data' => $data]);

            //     $mailer->send($mail);
            //     $this->addFlash('success', 'Votre email a bien été envoyé');
            //     return $this->redirectToRoute('contact.index');
            // } catch (\Exception $e) {
            //     $this->addFlash('danger', 'Impossible d\'envoyer votre email');
            // }
        }

        return $this->render('contact/index.html.twig', [
            'form' => $form,
        ]);
    }
}
