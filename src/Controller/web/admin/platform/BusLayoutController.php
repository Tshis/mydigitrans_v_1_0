<?php

declare(strict_types=1);

namespace App\Controller\web\admin\platform;

use App\Controller\web\admin\AbstractAdminController;
use App\Entity\BusLayout;
use App\Enum\Scope;
use App\Service\BusLayoutGridBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class BusLayoutController extends AbstractAdminController
{
    public function __construct(
        private readonly EntityManagerInterface $em
    ) {}

    protected function scope(): Scope
    {
        return Scope::Platform;
    }

    #[Route('/admin/platform/bus-layouts', name: 'admin_platform_bus_layout_index', methods: ['GET'])]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('bus_layout.view');
        $this->actor();

        $layoutsList = $this->em->getRepository(BusLayout::class)->findBy(
            ['agency' => null],
            ['createdAt' => 'DESC']
        );

        return $this->render('admin/platform/bus_layout/index.html.twig', [
            'page' => 'bus_layout',
            'layouts_list' => $layoutsList,
        ]);
    }

    #[Route('/admin/platform/bus-layout/add', name: 'admin_platform_bus_layout_add', methods: ['GET', 'POST'])]
    public function add(Request $request): Response
    {
        $this->denyAccessUnlessGranted('bus_layout.write');
        $this->actor();

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('bus_layout_add', (string) $request->request->get('_token'))) {
                throw new BadRequestHttpException('Jeton CSRF invalide.');
            }

            $allData = $request->request->all();
            $name = trim((string) ($allData['name'] ?? ''));
            $rows = (int) ($allData['rows'] ?? 0);
            $columns = (int) ($allData['columns'] ?? 0);

            if ('' === $name || $rows < 2 || $columns < 2) {
                $this->addFlash('error', 'Veuillez renseigner un nom valide ainsi que les dimensions (minimum 2x2).');
                return $this->redirectToRoute('admin_platform_bus_layout_add');
            }

            $specialPositions = [];
            $types = $allData['specialPositionType'] ?? [];
            $specRows = $allData['specialPositionRow'] ?? [];
            $specCols = $allData['specialPositionCol'] ?? [];

            foreach ($types as $index => $type) {
                if ('' !== trim((string) $type)) {
                    $specialPositions[] = [
                        'type' => (string) $type,
                        'row'  => (int) ($specRows[$index] ?? 0),
                        'col'  => (int) ($specCols[$index] ?? 0),
                    ];
                }
            }

            $aisles = array_values(array_unique(array_map('intval', $allData['aisles'] ?? [])));
            sort($aisles);

            $busLayout = new BusLayout();
            $busLayout->setName($name);
            $busLayout->setCode($this->generateUniqueCode());
            $busLayout->setAgency(null);
            $busLayout->setRows($rows);
            $busLayout->setColumns($columns);
            $busLayout->setAisles($aisles);
            $busLayout->setSpecialPositions($specialPositions);
            $busLayout->setHasBackExtraSeat(isset($allData['backExtraSeat']));
            $busLayout->setIsValidated(true); // 🟢 Aligné sur setIsValidated() de ton entité

            $this->em->persist($busLayout);
            $this->em->flush();

            $this->addFlash('success', sprintf('Le gabarit d\'usine « %s » (%s) a été enregistré.', $name, $busLayout->getCode()));
            return $this->redirectToRoute('admin_platform_bus_layout_index');
        }

        return $this->render('admin/platform/bus_layout/add.html.twig', [
            'page' => 'bus_layout',
        ]);
    }

    #[Route('/admin/platform/bus-layouts/{code}/modification', name: 'admin_platform_bus_layout_edit', methods: ['GET', 'POST'])]
    public function edit(
        #[MapEntity(mapping: ['code' => 'code'])] BusLayout $layout,
        Request $request
    ): Response {
        $this->denyAccessUnlessGranted('bus_layout.write');
        $this->actor();

        if (null !== $layout->getAgency()) {
            throw $this->createAccessDeniedException('Ce gabarit appartient à une agence.');
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('bus_layout_edit_' . $layout->getCode(), (string) $request->request->get('_token'))) {
                throw new BadRequestHttpException('Jeton CSRF invalide.');
            }

            $newName = trim((string) $request->request->get('name'));
            if ('' === $newName) {
                $this->addFlash('error', 'La désignation du gabarit est obligatoire.');
                return $this->redirectToRoute('admin_platform_bus_layout_edit', ['code' => $layout->getCode()]);
            }

            $layout->setName($newName);
            $this->em->flush();

            $this->addFlash('success', 'Le libellé commercial du modèle a été mis à jour.');
            return $this->redirectToRoute('admin_platform_bus_layout_index');
        }

        return $this->render('admin/platform/bus_layout/edit.html.twig', [
            'page' => 'bus_layout',
            'layout' => $layout,
        ]);
    }

    #[Route('/admin/platform/bus-layouts/{code}/details', name: 'admin_platform_bus_layout_show', methods: ['GET'])]
    public function show(
        #[MapEntity(mapping: ['code' => 'code'])] BusLayout $layout,
        BusLayoutGridBuilder $busLayoutGridBuilder
    ): Response {
        $this->denyAccessUnlessGranted('bus_layout.view');
        $this->actor();

        if (null !== $layout->getAgency()) {
            throw $this->createAccessDeniedException('Ce gabarit appartient à une agence.');
        }

        return $this->render('admin/platform/bus_layout/show.html.twig', [
            'page' => 'bus_layout',
            'layout' => $layout,
            'seatmap' => $busLayoutGridBuilder->build($layout->toArray()), // 🟢 Utilise ta méthode native toArray()
        ]);
    }

    #[Route('/admin/platform/bus-layouts/{code}/toggle/active', name: 'admin_platform_bus_layout_toggle', methods: ['POST'])]
    public function toggleLayout(
        #[MapEntity(mapping: ['code' => 'code'])] BusLayout $layout,
        Request $request
    ): Response {
        $this->denyAccessUnlessGranted('bus_layout.write');
        $this->actor();

        if (null !== $layout->getAgency()) {
            throw $this->createAccessDeniedException('Ce gabarit appartient à une agence.');
        }

        if (!$this->isCsrfTokenValid('bus_layout_toggle_' . $layout->getCode(), (string) $request->request->get('_token'))) {
            throw new BadRequestHttpException('Jeton CSRF invalide.');
        }

        $layout->setIsValidated(!$layout->isValidated()); // 🟢 Aligné sur isValidated()
        $this->em->flush();

        $this->addFlash(
            'success',
            $layout->isValidated() ? 'Le modèle d\'usine a été réactivé.' : 'Le modèle d\'usine a été désactivé.'
        );

        return $this->redirectToRoute('admin_platform_bus_layout_show', ['code' => $layout->getCode()]);
    }

    private function generateUniqueCode(): string
    {
        $alphabet = 'ABCDEFGHJKMNPQRSTVWXYZ23456789';
        $repo = $this->em->getRepository(BusLayout::class);

        for ($attempt = 0; $attempt < 10; ++$attempt) {
            $code = 'LYT-';
            for ($i = 0; $i < 6; ++$i) {
                $code .= $alphabet[random_int(0, \strlen($alphabet) - 1)];
            }
            if (null === $repo->findOneBy(['code' => $code])) {
                return $code;
            }
        }

        throw new \RuntimeException('Impossible de générer un code gabarit unique.');
    }
}
