<?php

declare(strict_types=1);

namespace App\Command\Catalog;

use App\Entity\Core\Website;
use App\Entity\Media\Media;
use App\Entity\Module\Catalog\Feature;
use App\Entity\Module\Catalog\FeatureIntl;
use App\Entity\Module\Catalog\FeatureValue;
use App\Entity\Module\Catalog\FeatureValueIntl;
use App\Entity\Module\Catalog\FeatureValueProduct;
use App\Entity\Module\Catalog\Product;
use App\Entity\Module\Catalog\ProductMediaRelation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * ProductEnrichCommand.
 *
 * Mise à jour de données produit sans régénération de la base :
 *  - titre : encadre le second mot (et suivants) d'un <span> pour le rendu kicker + script ;
 *  - galerie : complète les médias de chaque chambre avec des visuels de salle de bain ;
 *  - accordéons : aligne la taxonomie des caractéristiques sur la maquette
 *    (Confort / Literie / Salle de bain / Technologie), la Superficie étant conservée.
 *
 * Idempotente : rejouable après un doctrine:fixtures:load.
 *
 * @author Sébastien FOURNIER <fournier.sebastien@outlook.com>
 */
#[AsCommand(name: 'app:catalog:enrich-products', description: 'Titre span + galerie + accordéons maquette pour les chambres (sans regen DB)')]
final class ProductEnrichCommand extends Command
{
    /** Visuels de galerie ajoutés à chaque chambre (existants en base). */
    private const GALLERY_FILENAMES = [
        'chambre-salle-de-bain.jpg',
        'chambre-suite-sdb-6.jpg',
        'chambre-suite-sdb-6-1.jpg',
        'chambre-suite-sdb-6-2.jpg',
    ];

    /** Nombre total de médias visés par chambre (maquette : « 1 / 4 »). */
    private const TARGET_MEDIAS = 4;

    /** Caractéristique conservée hors accordéons (affichée en « 17 M² »). */
    private const SURFACE_FEATURE = 'Superficie';

    /** Accordéons maquette (feature => valeurs), communs à toutes les chambres. Ordre = ordre d'affichage. */
    private const array FEATURE_TAXONOMY = [
        'Confort' => ['Isolation phonique', 'Climatisation', 'Minibar', 'Machine Nespresso', 'Coffre-fort avec prise intégrée'],
        'Literie' => ['Literie king size', 'Linge de lit haut de gamme', "Choix d'oreillers"],
        'Salle de bain' => ["Douche à l'italienne", 'Peignoirs & chaussons', 'Sèche-cheveux', "Produits d'accueil"],
        'Technologie' => ['Wi-Fi gratuit', 'TV écran plat', 'Enceinte Marshall (Bluetooth)'],
    ];

    /** Slugs EN des features (convention projet). */
    private const array FEATURE_SLUGS = [
        'Confort' => 'comfort',
        'Literie' => 'bedding',
        'Salle de bain' => 'bathroom',
        'Technologie' => 'technology',
    ];

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $products = $this->entityManager->getRepository(Product::class)->findAll();

        if (!$products) {
            $io->warning('Aucun produit trouvé.');

            return Command::SUCCESS;
        }

        $website = $products[0]->getWebsite();
        $locale = $website?->getConfiguration()?->getLocale() ?? 'fr';
        $featureMap = $this->syncFeatures($website, $locale);

        $titlesUpdated = 0;
        $mediasAdded = 0;
        $productsReassigned = 0;

        foreach ($products as $product) {
            $titlesUpdated += $this->wrapTitle($product);
            $mediasAdded += $this->fillGallery($product);
            $productsReassigned += $this->reassignFeatures($product, $featureMap);
        }

        $this->entityManager->flush();
        $io->success(sprintf(
            '%d titre(s), %d image(s) de galerie, %d chambre(s) réaffectée(s) aux accordéons maquette, sur %d chambre(s).',
            $titlesUpdated,
            $mediasAdded,
            $productsReassigned,
            count($products)
        ));

        return Command::SUCCESS;
    }

    /**
     * Crée (si absentes) les 4 features maquette + leurs valeurs, et retourne la map nom => Feature.
     *
     * @return array<string, Feature>
     */
    private function syncFeatures(?Website $website, string $locale): array
    {
        $repository = $this->entityManager->getRepository(Feature::class);
        $map = [];
        $position = 0;

        foreach (self::FEATURE_TAXONOMY as $name => $values) {
            ++$position;
            $feature = $repository->findOneBy(['website' => $website, 'adminName' => $name]);

            if (!$feature instanceof Feature) {
                $feature = new Feature();
                $feature->setAdminName($name);
                $feature->setSlug(self::FEATURE_SLUGS[$name]);
                $feature->setWebsite($website);
                $feature->setPosition($position);
                $feature->setCreatedAt(new \DateTime('now', new \DateTimeZone('Europe/Paris')));
                $intl = new FeatureIntl();
                $intl->setLocale($locale);
                $intl->setTitle($name);
                $intl->setWebsite($website);
                $feature->addIntl($intl);
                $this->entityManager->persist($intl);
                $this->entityManager->persist($feature);
            }

            $existing = [];
            foreach ($feature->getValues() as $value) {
                $existing[$value->getAdminName()] = true;
            }

            $valuePosition = 0;
            foreach ($values as $label) {
                ++$valuePosition;
                if (isset($existing[$label])) {
                    continue;
                }
                $value = new FeatureValue();
                $value->setAdminName($label);
                $value->setSlug(self::FEATURE_SLUGS[$name].'-'.$valuePosition);
                $value->setWebsite($website);
                $value->setPosition($valuePosition);
                $value->setCreatedAt(new \DateTime('now', new \DateTimeZone('Europe/Paris')));
                $intl = new FeatureValueIntl();
                $intl->setLocale($locale);
                $intl->setTitle($label);
                $intl->setWebsite($website);
                $value->addIntl($intl);
                $feature->addValue($value);
                $this->entityManager->persist($intl);
                $this->entityManager->persist($value);
            }

            $map[$name] = $feature;
        }

        $this->entityManager->flush();

        return $map;
    }

    /**
     * Réaffecte une chambre aux 4 accordéons maquette : retire les liens hors Superficie
     * (ancienne taxonomie) et rattache toutes les valeurs des features cibles.
     *
     * @param array<string, Feature> $featureMap
     */
    private function reassignFeatures(Product $product, array $featureMap): int
    {
        foreach ($product->getValues()->toArray() as $valueProduct) {
            $featureName = $valueProduct->getFeature()?->getAdminName()
                ?? $valueProduct->getValue()?->getCatalogfeature()?->getAdminName();
            if (self::SURFACE_FEATURE === $featureName) {
                continue;
            }
            $product->removeValue($valueProduct);
            $this->entityManager->remove($valueProduct);
        }

        // Positions suivant l'ordre de la taxonomie (maquette) : Product::$values est trié
        // par position ASC, donc l'ordre des accordéons ET des items en découle.
        $position = 0;
        foreach (self::FEATURE_TAXONOMY as $name => $labels) {
            $feature = $featureMap[$name];
            $byLabel = [];
            foreach ($feature->getValues() as $value) {
                $byLabel[$value->getAdminName()] = $value;
            }
            foreach ($labels as $label) {
                $value = $byLabel[$label] ?? null;
                if (!$value instanceof FeatureValue) {
                    continue;
                }
                ++$position;
                $valueProduct = new FeatureValueProduct();
                $valueProduct->setValue($value);
                $valueProduct->setFeature($feature);
                $valueProduct->setProduct($product);
                $valueProduct->setPosition($position);
                $valueProduct->setFeaturePosition($position);
                $valueProduct->setCreatedAt(new \DateTime('now', new \DateTimeZone('Europe/Paris')));
                $product->addValue($valueProduct);
                $this->entityManager->persist($valueProduct);
            }
        }

        return 1;
    }

    /**
     * Encadre le second mot (et suivants) du titre d'un <span>, pour toutes les locales.
     */
    private function wrapTitle(Product $product): int
    {
        $count = 0;

        foreach ($product->getIntls() as $intl) {
            $title = (string) $intl->getTitle();
            if ('' === $title || str_contains($title, '<span')) {
                continue;
            }

            $parts = explode(' ', trim($title), 2);
            if (2 !== count($parts)) {
                continue;
            }

            $intl->setTitle(sprintf('%s <span>%s</span>', $parts[0], mb_strtolower($parts[1])));
            ++$count;
        }

        return $count;
    }

    /**
     * Complète la galerie avec des visuels de salle de bain (jusqu'à TARGET_MEDIAS).
     */
    private function fillGallery(Product $product): int
    {
        $relations = $product->getMediaRelations();
        $total = $relations->count();
        if ($total >= self::TARGET_MEDIAS) {
            return 0;
        }

        $locale = null;
        $attached = [];
        foreach ($relations as $relation) {
            $locale = $locale ?? $relation->getLocale();
            if ($relation->getMedia()) {
                $attached[] = $relation->getMedia()->getFilename();
            }
        }
        $locale = $locale ?? $product->getWebsite()?->getConfiguration()?->getLocale();

        $position = $total;
        $added = 0;

        foreach (self::GALLERY_FILENAMES as $filename) {
            if ($total >= self::TARGET_MEDIAS) {
                break;
            }
            if (in_array($filename, $attached, true)) {
                continue;
            }

            $media = $this->entityManager->getRepository(Media::class)->findOneBy([
                'website' => $product->getWebsite(),
                'filename' => $filename,
            ]);
            if (!$media instanceof Media) {
                continue;
            }

            $relation = new ProductMediaRelation();
            $relation->setLocale($locale);
            $relation->setMedia($media);
            $relation->setMain(false);
            $relation->setPopup(false);
            $relation->setDownloadable(false);
            $relation->setPosition(++$position);
            $product->addMediaRelation($relation);
            $this->entityManager->persist($relation);

            ++$total;
            ++$added;
        }

        return $added;
    }
}
