<?php

namespace App\Controller\web\public;

use App\Form\LineSearchType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

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
    } //index




    #[Route('/contact', name: 'home_contact')]
    public function contact(): Response
    {
        return $this->render('public/home/contact.html.twig', [
            'page' => 'contacts',
        ]);
    } //contact




}
