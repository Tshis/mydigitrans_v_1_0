<?php

namespace App\Controller\web\public;

use App\Dto\public\home\ContactData;
use App\Form\ContactType;
use App\Form\LineSearchType;
use App\Service\ContactMailer;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\DependencyInjection\Attribute\Target; // <-- AJOUT

final class HomeController extends AbstractController
{
    #[Route('/', name: 'home_index')]
    public function index(Request $request): Response
    {
        $form = $this->createForm(LineSearchType::class);
        $form->handleRequest($request);

        return $this->render('public/home/index.html.twig', [
            'page' => 'accueil',
        ]);
    }

    /**
     * PAGE DE CONTACT : valide le message puis l'envoie par email à l'équipe.
     * Protections : jeton CSRF, champ piège à robots et limite d'envois par adresse IP.
     */
    #[Route('/contact', name: 'home_contact', methods: ['GET', 'POST'])]
    public function contact(
        Request $request,
        ContactMailer $contactMailer,
        #[Target('contact')] RateLimiterFactoryInterface $contactLimiter,
        LoggerInterface $logger
    ): Response {
        $data = new ContactData();
        $form = $this->createForm(ContactType::class, $data);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ('' !== trim((string) $data->website)) {
                $this->addFlash('success', 'Votre message a bien été envoyé. Nous vous répondrons dans les plus brefs délais.');

                return $this->redirectToRoute('home_contact');
            }

            $limit = $contactLimiter->create($request->getClientIp() ?? 'unknown')->consume();

            if (!$limit->isAccepted()) {
                // CORRECTION : On passe une chaîne de caractères pure au flash message
                $this->addFlash('error', 'Vous avez envoyé trop de messages. Merci de réessayer plus tard.');

                return $this->redirectToRoute('home_contact');
            }

            try {
                $contactMailer->send($data, $request->getClientIp(), new \DateTimeImmutable());
                $this->addFlash('success', 'Votre message a bien été envoyé. Nous vous répondrons dans les plus brefs délais.');

                return $this->redirectToRoute('home_contact');
            } catch (TransportExceptionInterface $e) {
                $logger->error('Échec de l\'envoi du message de contact.', ['exception' => $e]);

                // CORRECTION : On passe une chaîne de caractères pure au flash message
                $this->addFlash('error', 'Votre message n\'a pas pu être envoyé. Merci de réessayer dans quelques instants ou de nous écrire directement par email.');

                return $this->redirectToRoute('home_contact');
            }
        }

        return $this->render('public/home/contact.html.twig', [
            'page' => 'contacts',
            'form' => $form->createView(),
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }
}
