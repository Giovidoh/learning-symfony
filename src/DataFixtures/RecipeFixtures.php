<?php

namespace App\DataFixtures;

use App\Entity\Category;
use App\Entity\Recipe;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use FakerRestaurant\Provider\fr_FR\Restaurant;
use Override;
use Symfony\Component\String\Slugger\SluggerInterface;

class RecipeFixtures extends Fixture implements DependentFixtureInterface
{
    public function __construct(private readonly SluggerInterface $slugger) {}

    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');
        $faker->addProvider(new Restaurant($faker));

        $categories = ['Plat chaud', 'Dessert', 'Entrée', 'Goûter'];
        foreach ($categories as $c) {
            $timestamp = \DateTimeImmutable::createFromMutable($faker->dateTime());
            $category = (new Category())
                ->setName($c)
                ->setSlug(strtolower($this->slugger->slug($c)))
                ->setUpdatedAt($timestamp)
                ->setCreatedAt($timestamp);
            $manager->persist($category);
            $this->addReference($c, $category);
        }

        for ($i = 1; $i <= 10; $i++) {
            $title = $faker->unique()->foodName();
            $timestamp = \DateTimeImmutable::createFromMutable($faker->dateTime());
            $recipe = new Recipe()
                ->setTitle($title)
                ->setSlug(strtolower($this->slugger->slug($title)))
                ->setCreatedAt($timestamp)
                ->setUpdatedAt($timestamp)
                ->setContent($faker->paragraphs(10, true))
                ->setCategory($this->getReference($faker->randomElement($categories), Category::class))
                ->setUser($this->getReference('USER' . $faker->numberBetween(1, 10), User::class))
                ->setDuration($faker->numberBetween(2, 60));
            $manager->persist($recipe);
        }

        $manager->flush();
    }

    #[Override]
    public function getDependencies(): array
    {
        return [UserFixtures::class];
    }
}
