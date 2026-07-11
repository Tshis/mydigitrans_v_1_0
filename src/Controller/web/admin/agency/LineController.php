<?php

namespace App\Controller\web\admin\agency;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\CountryType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class LineController extends AbstractController
{
    #[Route('/admin/agency/lines', name: 'admin_agency_line_index')]
    public function index(): Response
    {
        return $this->render('admin/agency/line/index.html.twig', [
            'page' => 'line',
        ]);
    } //index

    #[Route('/admin/agency/add', name: 'admin_agency_line_add')]
    public function add(Request $request): Response
    {
        // 1. Création du formulaire à la volée (sans entité)
        $form = $this->createFormBuilder()
            ->add('paysOrigine', CountryType::class, [
                'placeholder' => 'Rechercher un pays...',
                'autocomplete' => true, // Option Symfony UX
            ])
            ->add('paysArrive', CountryType::class, [
                'placeholder' => 'Rechercher un pays...',
                'autocomplete' => true, // Option Symfony UX
            ])
            ->getForm();

        if ($request->isMethod('POST')) {
            // Traitement à venir lorsque nous brancherons la base de données
            $this->addFlash('success', 'Trajet enregistré avec succès (Simulation) !');
            return $this->redirectToRoute('admin_agency_line_index');
        }

        return $this->render('admin/agency/line/add.html.twig', [
            'page' => 'line',
            'form' => $form->createView(),
        ]);
    } //add
}
