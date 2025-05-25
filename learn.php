<?php
include('includes/db.php');
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$message = '';
$messageType = '';

// Educational articles data with much longer, comprehensive content
$articles = [
    [
        "id" => "blood-types",
        "title" => "The Complete Guide to Blood Types: Understanding Your Genetic Legacy",
        "category" => "Blood Types & Genetics",
        "excerpt" => "Dive deep into the fascinating world of blood types, their discovery, genetic inheritance, and impact on health, personality, and medical treatment.",
        "image" => "https://images.unsplash.com/photo-1559757148-5c350d0d3c56?w=1200&h=600&fit=crop&crop=center",
        "read_time" => "12 min read",
        "featured" => true,
        "author" => "Dr. Maria Santos, MD, Hematologist",
        "date" => "2024-01-15",
        "tags" => ["genetics", "health", "transfusion", "compatibility"],
        "content" => [
            "introduction" => "Blood types represent one of the most important discoveries in modern medicine, revolutionizing transfusion therapy and organ transplantation. Beyond their medical significance, blood types offer fascinating insights into human evolution, genetics, and even cultural beliefs about personality traits. This comprehensive guide explores everything you need to know about blood types, from the basic science to cutting-edge research.",
            "sections" => [
                [
                    "title" => "The Revolutionary Discovery: Karl Landsteiner's Legacy",
                    "content" => "In 1901, Austrian physician Karl Landsteiner made a discovery that would save millions of lives and earn him the Nobel Prize in Physiology or Medicine. While investigating why some blood transfusions were successful while others proved fatal, Landsteiner discovered that human blood could be classified into distinct groups based on the presence or absence of specific proteins called antigens on red blood cells. This groundbreaking work laid the foundation for safe blood transfusions and modern transplant medicine. Before this discovery, blood transfusions were essentially a deadly gamble, with success rates barely above chance. Landsteiner's systematic approach involved mixing blood samples from different individuals and observing reactions under a microscope. He noticed that some combinations caused blood cells to clump together (agglutinate), while others remained smooth. This observation led to the identification of the ABO blood group system, which remains the most clinically significant blood typing system today.",
                    "image" => "https://images.unsplash.com/photo-1576091160550-2173dba999ef?w=800&h=500&fit=crop&crop=center"
                ],
                [
                    "title" => "Type O: The Universal Life-Saver",
                    "content" => "Type O blood, characterized by the absence of both A and B antigens on red blood cells, represents approximately 45% of the global population, making it the most common blood type worldwide. However, this percentage varies dramatically across different populations and geographic regions. In some Native American populations, Type O can represent up to 100% of individuals, while in certain Asian populations, it may be as low as 30%. The 'universal donor' designation for Type O negative blood makes it incredibly valuable in emergency situations where there's no time for cross-matching. Emergency rooms and trauma centers maintain constant supplies of O-negative blood precisely because it can be transfused to any patient regardless of their blood type. However, this universality only applies to red blood cell transfusions. When it comes to plasma transfusions, Type O individuals can only receive Type O plasma due to the presence of both anti-A and anti-B antibodies in their plasma. From an evolutionary perspective, Type O is believed to be the oldest blood type, possibly emerging over 60,000 years ago. Some researchers theorize that the lack of A and B antigens may have provided survival advantages against certain infectious diseases, particularly those affecting the digestive system.",
                    "facts" => [
                        "Can donate red blood cells to all blood types, earning the title 'universal donor'",
                        "Can only receive red blood cells from other Type O individuals",
                        "Most common blood type globally, representing 45% of the world's population",
                        "May have increased risk of stomach ulcers due to higher stomach acid production",
                        "Potentially better resistance to malaria and certain other parasitic infections",
                        "Lower risk of pancreatic cancer compared to other blood types",
                        "May have enhanced blood clotting ability, reducing bleeding risk during surgery",
                        "Associated with lower levels of von Willebrand factor, affecting blood clotting"
                    ]
                ],
                [
                    "title" => "Type A: The Methodical Majority",
                    "content" => "Type A blood, distinguished by the presence of A antigens on red blood cells and anti-B antibodies in the plasma, accounts for approximately 40% of the global population. This blood type shows remarkable geographic variation, being particularly prevalent in Western Europe (where it can reach 50% in some populations) and parts of Asia. The evolutionary emergence of Type A is estimated to have occurred between 25,000 and 15,000 years ago, possibly coinciding with the development of agriculture and more sedentary lifestyles. This timing has led some researchers to speculate about connections between blood type and dietary adaptation. Individuals with Type A blood can safely donate to both Type A and Type AB recipients, while they can receive transfusions from Type A and Type O donors. From a medical perspective, Type A individuals may have different susceptibility patterns to various diseases. Research suggests they might have slightly higher risks of cardiovascular disease and certain types of cancer, but lower risks of kidney stones. The presence of A antigens may also influence how the immune system responds to certain pathogens. Some studies indicate that Type A individuals might be more susceptible to certain viral infections, including some strains of influenza and potentially COVID-19, though research in this area is ongoing and complex.",
                    "facts" => [
                        "Can donate to Type A and Type AB recipients safely",
                        "Can receive transfusions from Type A and Type O donors",
                        "May have naturally higher cortisol levels, potentially affecting stress response",
                        "Possibly lower stomach acid production, affecting digestion",
                        "More prevalent in European and some Asian populations",
                        "May have increased risk of coronary heart disease",
                        "Potentially higher susceptibility to certain viral infections",
                        "Associated with different gut microbiome compositions"
                    ]
                ],
                [
                    "title" => "Type B: The Adaptable Individualists",
                    "content" => "Type B blood, characterized by B antigens on red blood cells and anti-A antibodies in plasma, represents about 11% of the global population. However, this distribution is far from uniform across the world. Type B blood is significantly more common in parts of Asia, particularly in populations from the Indian subcontinent and Central Asia, where it can represent 25-30% of individuals. Conversely, it's relatively rare in Native American populations and parts of Western Europe. The evolutionary timeline suggests Type B blood emerged approximately 10,000-15,000 years ago, possibly in the Himalayan region, and spread through population migrations and cultural exchanges along ancient trade routes. This geographic and temporal pattern has led to interesting anthropological studies about human migration patterns. From a transfusion standpoint, Type B individuals can donate to both Type B and Type AB recipients, while they can safely receive blood from Type B and Type O donors. Medically, Type B individuals appear to have some unique characteristics. Some research suggests they may have more flexible immune systems, potentially better equipped to handle environmental changes and stressors. There's also evidence suggesting Type B individuals might have different susceptibilities to autoimmune conditions and certain infectious diseases.",
                    "facts" => [
                        "Can donate to Type B and Type AB recipients",
                        "Can receive from Type B and Type O donors safely",
                        "More flexible immune system adaptation to environmental changes",
                        "May handle psychological stress more effectively",
                        "Significantly more common in Asian and some African populations",
                        "Potentially different susceptibility to autoimmune diseases",
                        "May have enhanced resistance to certain bacterial infections",
                        "Associated with different neurochemical patterns affecting mood"
                    ]
                ],
                [
                    "title" => "Type AB: The Rare Universal Recipients",
                    "content" => "Type AB blood represents the newest and rarest of the ABO blood types, comprising only 4% of the global population. This blood type is characterized by the presence of both A and B antigens on red blood cells, with no anti-A or anti-B antibodies in the plasma. From an evolutionary perspective, Type AB is believed to be the most recent blood type, emerging less than 1,000 years ago through the intermingling of Type A and Type B populations. This relatively recent emergence explains its rarity and uneven global distribution. Type AB individuals hold the unique distinction of being 'universal recipients' for red blood cell transfusions, meaning they can safely receive blood from donors of any ABO type. However, this universality comes with a trade-off: they can only donate red blood cells to other Type AB individuals, limiting their ability to help others through blood donation. Interestingly, when it comes to plasma donation, the situation reverses entirely. Type AB plasma contains no anti-A or anti-B antibodies, making it compatible with all blood types, earning Type AB individuals the title 'universal plasma donors.' This makes their plasma particularly valuable for treating trauma patients and those with certain bleeding disorders. Research into Type AB health characteristics reveals some intriguing patterns. Some studies suggest Type AB individuals might have increased risks of cognitive decline and memory problems as they age, possibly related to higher levels of certain clotting factors in their blood.",
                    "facts" => [
                        "Can receive red blood cells from all ABO blood types (universal recipient)",
                        "Can only donate red blood cells to other Type AB individuals",
                        "Universal plasma donor - AB plasma can be given to all blood types",
                        "Rarest blood type, representing only 4% of the population",
                        "May have increased risk of cognitive decline with aging",
                        "Potentially higher levels of certain blood clotting factors",
                        "Most recent blood type from an evolutionary perspective",
                        "May have unique metabolic characteristics affecting nutrient processing"
                    ]
                ],
                [
                    "title" => "The Rh Factor: The Critical Plus and Minus",
                    "content" => "The Rh factor, named after the Rhesus monkey in which it was first discovered in 1940, represents another crucial component of blood typing. This protein, found on the surface of red blood cells in about 85% of people (Rh-positive), adds an additional layer of complexity to blood compatibility. The remaining 15% of the population lacks this protein and is classified as Rh-negative. The Rh system is actually much more complex than the simple positive/negative designation suggests, involving over 50 different antigens, though the D antigen is by far the most clinically significant. The discovery of the Rh factor solved the mystery of why some apparently compatible ABO transfusions still resulted in severe reactions. The clinical importance of Rh factor becomes particularly critical during pregnancy. When an Rh-negative mother carries an Rh-positive baby (a situation that occurs in about 10% of pregnancies), there's potential for Rh incompatibility. If fetal blood crosses into the maternal circulation during pregnancy or delivery, the mother's immune system may produce antibodies against the Rh factor, treating it as a foreign invader. While this typically doesn't affect the first pregnancy, subsequent Rh-positive pregnancies can be severely threatened by these maternal antibodies attacking the fetal blood cells, leading to a condition called hemolytic disease of the newborn.",
                    "image" => "https://images.unsplash.com/photo-1559757175-0eb30cd8c063?w=800&h=500&fit=crop&crop=center"
                ],
                [
                    "title" => "Beyond ABO and Rh: The Complex World of Blood Antigens",
                    "content" => "While ABO and Rh represent the most clinically significant blood group systems, the complete picture of human blood typing is far more complex. Scientists have identified over 300 blood group antigens organized into more than 30 blood group systems. These include the Kell system, Duffy system, Kidd system, MNS system, and many others. Each system represents different proteins or carbohydrates on red blood cell surfaces, inherited according to specific genetic patterns. These 'minor' blood groups can become critically important in certain situations. For patients requiring multiple transfusions, such as those with sickle cell disease or thalassemia, matching these additional antigens becomes crucial to prevent alloimmunization - the development of antibodies against foreign antigens. Some of these blood group systems also have fascinating connections to disease resistance. For example, individuals lacking certain Duffy antigens show resistance to specific types of malaria, which explains why this trait is more common in populations from malaria-endemic regions.",
                    "facts" => [
                        "Over 300 different blood antigens have been identified",
                        "Organized into more than 30 distinct blood group systems",
                        "Critical for patients requiring multiple transfusions",
                        "Some provide natural resistance to specific diseases",
                        "Geographic distribution varies based on evolutionary pressures",
                        "Can affect organ transplant compatibility",
                        "Important for forensic science and paternity testing",
                        "Continue to be discovered through advanced genetic testing"
                    ]
                ]
            ]
        ]
    ],
    [
        "id" => "donation-journey",
        "title" => "The Incredible Journey: From Donor to Life-Saving Transfusion",
        "category" => "Donation Process",
        "excerpt" => "Follow the remarkable 24 to 42-day journey of donated blood through collection, processing, testing, storage, and ultimately saving lives in hospitals worldwide.",
        "image" => "https://redcross.org.ph/wp-content/uploads/2019/06/PR.jpg",
        "read_time" => "15 min read",
        "featured" => true,
        "author" => "Dr. Patricia Valdez, MD, Transfusion Medicine Specialist",
        "date" => "2024-01-20",
        "tags" => ["donation", "processing", "safety", "hospitals"],
        "content" => [
            "introduction" => "Every unit of donated blood embarks on an extraordinary journey that spans weeks and involves dozens of dedicated professionals, cutting-edge technology, and rigorous safety protocols. From the moment a needle enters a donor's arm to the life-saving transfusion in a hospital room, blood undergoes a transformation that exemplifies the best of modern medicine and human compassion. This comprehensive exploration follows that journey step by step, revealing the science, technology, and human dedication that makes blood transfusion one of medicine's greatest achievements.",
            "sections" => [
                [
                    "title" => "The Sacred Act: Blood Collection and the Donor Experience",
                    "content" => "The journey begins with a single act of altruism - a donor's decision to give the gift of life. The collection process, refined over decades, balances efficiency with donor comfort and safety. Upon arrival at a blood center or mobile drive, donors undergo a comprehensive screening process that includes a detailed health questionnaire, mini-physical examination, and rapid hemoglobin test. This screening, which takes 15-30 minutes, serves multiple purposes: protecting donor health, ensuring blood safety, and optimizing the donation experience. The actual blood collection takes 8-12 minutes during which approximately 450ml (about one pint) of whole blood is drawn through a sterile, single-use needle and collection system. The human body contains approximately 10-12 pints of blood, so this donation represents less than 10% of total blood volume. Modern collection bags contain anticoagulants and preservatives that immediately begin protecting the donated blood. The collection system is a closed, sterile environment that prevents contamination while allowing for the separation of blood into multiple components. During collection, small samples are simultaneously drawn for testing, ensuring that every drop of the main donation remains available for transfusion if tests prove satisfactory.",
                    "image" => "https://images.unsplash.com/photo-1559757148-5c350d0d3c56?w=800&h=500&fit=crop&crop=center"
                ],
                [
                    "title" => "The Transformation: Component Separation and Processing",
                    "content" => "Within 6-8 hours of collection, whole blood undergoes one of modern medicine's most elegant processes: component separation. Using high-speed centrifuges that spin at precisely controlled speeds, blood is separated into its constituent parts, each destined for different lifesaving applications. This process, called fractionation, typically yields three primary components from each donation. Red blood cells, the oxygen-carrying workhorses of the circulatory system, are separated and suspended in nutrient solutions that extend their shelf life to 35-42 days when stored at 1-6°C. These cells are crucial for treating anemia, blood loss from surgery or trauma, and various blood disorders. Plasma, the liquid portion comprising about 55% of blood volume, is separated and can be frozen for up to one year. Plasma contains hundreds of proteins, including clotting factors essential for treating hemophilia and other bleeding disorders. Platelets, the tiny cell fragments responsible for blood clotting, require special handling due to their delicate nature and short lifespan. They must be stored at room temperature with constant gentle agitation and have a shelf life of only 5 days. One unit of whole blood may yield enough platelets for a single transfusion, but often platelets from multiple donors are combined to create therapeutic doses.",
                    "facts" => [
                        "One donation can save up to three lives through component separation",
                        "Red blood cells can be stored for 35-42 days when properly preserved",
                        "Plasma can be frozen and stored for up to one year",
                        "Platelets have the shortest shelf life at only 5 days",
                        "Component separation occurs within 6-8 hours of collection",
                        "Each component serves different medical purposes and patient needs",
                        "Advanced processing can create up to 8 different products from one donation"
                    ]
                ]
            ]
        ]
    ],
    [
        "id" => "global-impact",
        "title" => "Blood Donation Worldwide: Global Challenges and Triumphs",
        "category" => "Global Health",
        "excerpt" => "Explore blood donation practices, challenges, and innovations across different countries and cultures.",
        "image" => "https://images.unsplash.com/photo-1559757175-0eb30cd8c063?w=1200&h=600&fit=crop&crop=center",
        "read_time" => "18 min read",
        "featured" => true,
        "author" => "Dr. Robert Chen, Global Health Specialist",
        "date" => "2024-01-25",
        "tags" => ["global-health", "cultural-aspects", "innovation", "challenges"],
        "content" => [
            "introduction" => "Blood donation represents both humanity's capacity for compassion and the stark inequalities that persist in global healthcare. This comprehensive examination explores blood donation practices worldwide, cultural influences on donation rates, innovative solutions emerging from different regions, and ongoing efforts to ensure safe, adequate blood supplies for all humanity.",
            "sections" => [
                [
                    "title" => "The Global Blood Supply Crisis: Numbers That Tell a Story",
                    "content" => "The World Health Organization estimates that while 118.5 million blood donations are collected globally each year, the distribution is profoundly unequal. High-income countries, representing only 12% of the world's population, account for 65% of all blood donations. This disparity translates into stark differences in blood availability: developed nations typically maintain supplies of 36-40 donations per 1,000 people annually, while many developing countries struggle with fewer than 10 donations per 1,000 people. The WHO recommends a minimum of 10-20 donations per 1,000 population to meet basic transfusion needs, yet 73 countries fail to meet even this minimum threshold. Sub-Saharan Africa faces the most severe shortages, with some regions reporting fewer than 5 donations per 1,000 people despite having higher rates of conditions requiring transfusion, such as maternal hemorrhage, malaria, and sickle cell disease. These statistics represent more than numbers - they reflect a crisis where geography determines access to life-saving treatment. The consequences are measured in preventable deaths, with an estimated 500,000 women dying annually from pregnancy-related complications that could be treated with adequate blood supplies.",
                    "image" => "https://files01.pna.gov.ph/ograph/2022/02/02/dumaguete---blood-letting-bfp-jan-21-2022--bfp-photo.jpeg",
                    "facts" => [
                        "Only 42% of global blood donations come from developing countries with 82% of world population",
                        "73 countries collect fewer than 10 donations per 1,000 people annually",
                        "Sub-Saharan Africa has the lowest donation rates despite highest medical need",
                        "500,000 maternal deaths annually could be prevented with adequate blood supplies",
                        "Blood shortage affects 76 countries globally according to WHO data",
                        "Developed nations have 10x higher donation rates than least developed countries",
                        "Rural areas worldwide face disproportionate blood access challenges"
                    ]
                ],
                [
                    "title" => "Cultural Tapestry: How Beliefs Shape Blood Donation",
                    "content" => "Cultural attitudes toward blood donation vary dramatically across societies, creating fascinating patterns of acceptance and resistance. In Japan, blood donation is viewed as a civic duty, with the Japanese Red Cross organizing workplace campaigns that achieve impressive participation rates. The concept of 'social responsibility' deeply embedded in Japanese culture translates into one of the world's most reliable volunteer donor bases. Conversely, many traditional African societies view blood as containing the essence of life and family lineage, making donation psychologically challenging despite urgent medical needs. Islamic cultures generally support blood donation as a form of charity (sadaqah), with religious leaders actively promoting donation as fulfilling religious obligations to help others. However, some interpret religious texts as prohibiting the mixing of bloodlines, creating theological debates that blood bank educators must navigate carefully. Hindu traditions in India present complex attitudes - while helping others is a fundamental principle (dharma), some believe blood donation weakens the body's spiritual energy. These beliefs have led to innovative approaches, such as partnering with religious festivals where donation becomes part of spiritual observance. In Latin American cultures, strong family bonds sometimes create reluctance to donate to strangers when family members might need blood, leading to directed donation programs where families can contribute to community pools while ensuring their loved ones' needs are met.",
                    "facts" => [
                        "Japan achieves 99.5% voluntary donation rate through cultural integration",
                        "Religious endorsement increases donation rates by 40% in faith-based communities",
                        "Cultural taboos reduce donation participation by up to 60% in some regions",
                        "Family-directed donation programs show 3x higher participation in collectivist cultures",
                        "Educational campaigns using cultural messengers improve acceptance by 50%",
                        "Traditional medicine beliefs affect donation attitudes in 80% of developing nations",
                        "Gender roles influence donation patterns differently across cultural contexts"
                    ]
                ],
                [
                    "title" => "Innovation Born from Necessity: Developing World Solutions",
                    "content" => "Resource limitations have sparked remarkable innovations in blood collection and management across the developing world. In rural Kenya, motorcycle-based mobile collection units navigate difficult terrain to reach remote communities, while solar-powered refrigeration systems maintain blood storage in areas without reliable electricity. Ghana's National Blood Service pioneered a text messaging system that alerts registered donors when their specific blood type is urgently needed, achieving response rates that exceed traditional methods by 300%. Nigeria's innovative 'family replacement' system encourages relatives of patients to donate blood, creating a sustainable model that doesn't rely solely on altruistic voluntary donors. This approach has been adapted across West Africa with remarkable success. In Bangladesh, floating blood banks operate during monsoon seasons when flooding isolates communities, ensuring continuous access to life-saving blood products. India's unique 'blood on wheels' program uses converted buses equipped with complete collection and testing facilities, bringing sophisticated blood banking to rural villages. These mobile units can process donations immediately, reducing the cold chain requirements and improving efficiency. Rwanda's post-genocide blood service rebuild created one of Africa's most efficient systems, using drone technology to deliver blood products to remote hospitals within 30 minutes. These innovations demonstrate how constraint-driven creativity can produce solutions that are often more efficient and cost-effective than traditional approaches.",
                    "image" => "https://images.unsplash.com/photo-1584515933487-779824d29309?w=800&h=500&fit=crop&crop=center"
                ],
                [
                    "title" => "Digital Revolution: Technology Transforming Blood Banking",
                    "content" => "The digital revolution has transformed blood banking globally, with developing nations often leapfrogging traditional infrastructure. Kenya's iBlood platform connects donors, hospitals, and blood banks through mobile technology, creating a real-time network that has reduced critical shortages by 60%. The system sends automatic notifications to registered donors when their blood type is needed, tracks inventory across multiple facilities, and enables hospitals to request specific products instantly. China's national blood information system processes over 13 million donations annually, using artificial intelligence to predict demand patterns and optimize distribution networks. The system can forecast seasonal variations, account for regional differences in blood type distribution, and automatically trigger collection campaigns when shortages are predicted. In Brazil, blockchain technology ensures blood product traceability from donor to recipient, creating an immutable record that enhances safety and enables rapid response to any adverse events. This transparency has increased public trust in the blood supply system. Estonia's e-health integration allows citizens to schedule blood donations online, receive automatic health screening results, and track their donation history through a secure digital platform. The system has increased donor retention rates by 45% while reducing administrative costs by 30%. Mobile apps across multiple countries now gamify blood donation, creating point systems, social sharing features, and donor competitions that appeal to younger demographics who were previously difficult to engage.",
                    "facts" => [
                        "Mobile-based donor systems increase response rates by 250% in crisis situations",
                        "AI-powered demand forecasting reduces waste by 35% in large blood centers",
                        "Blockchain traceability systems process over 5 million units annually in pilot programs",
                        "Digital health integration improves donor retention by 40% on average",
                        "Mobile apps have attracted 2.3 million new donors globally since 2020",
                        "Automated inventory management reduces human error by 90% in participating centers",
                        "Real-time networking connects over 1,000 blood banks across 25 countries"
                    ]
                ]
            ]
        ]
    ],
    [
    "id" => "future-medicine",
    "title" => "The Future of Blood: Artificial Blood, Regenerative Medicine, and Beyond",
    "category" => "Medical Innovation",
    "excerpt" => "Discover cutting-edge research in artificial blood substitutes, lab-grown blood cells, and revolutionary technologies redefining the future of transfusion medicine.",
    "image" => "https://images.unsplash.com/photo-1559757148-5c350d0d3c56?w=1200&h=600&fit=crop&crop=center",
    "read_time" => "20 min read",
    "featured" => false,
    "author" => "Dr. Sarah Kim, Regenerative Medicine Researcher",
    "date" => "2024-01-30",
    "tags" => ["innovation", "research", "artificial-blood", "stem-cells", "regenerative-medicine", "future-of-healthcare"],
    "content" => [
        "introduction" => "The future of transfusion medicine stands at the threshold of revolutionary breakthroughs that could fundamentally transform how we think about blood replacement and regenerative therapy. From artificial blood substitutes to stem-cell-driven biomanufacturing, scientists are reimagining how we generate, store, and use this vital fluid. In a world plagued by donor shortages, rare blood types, and blood-borne diseases, innovation in blood science could save millions of lives.",
        "sections" => [
            [
                "title" => "Artificial Blood: Engineering Life's Most Complex Fluid",
                "content" => "The quest for artificial blood has captivated researchers for over a century, driven by the promise of unlimited supply, universal compatibility, and elimination of disease transmission risks. Modern approaches include perfluorocarbon-based substitutes and hemoglobin-based oxygen carriers (HBOCs), each with unique challenges in oxygen delivery, shelf stability, and biocompatibility. Companies like Hemarina and research institutions worldwide are racing to perfect synthetic blood that mimics human physiology without the risks.",
            ],
            [
                "title" => "Lab-Grown Blood: Stem Cells and Bioreactors",
                "content" => "Recent advances in stem cell biology have enabled the creation of red blood cells from induced pluripotent stem cells (iPSCs) and hematopoietic stem cells. This breakthrough could lead to customizable blood transfusions tailored to patients with rare antigens or chronic transfusion needs. Clinical trials are underway in the UK, Japan, and the U.S., aiming to demonstrate the safety and efficacy of lab-grown blood products in human patients.",
            ],
            [
                "title" => "Regenerative Medicine and the Hematopoietic Revolution",
                "content" => "Beyond transfusions, regenerative medicine envisions a world where damaged blood vessels and tissues can be repaired using bioengineered cells and scaffolds. Hematopoietic stem cell transplants have already transformed treatment for leukemia and genetic blood disorders. Future therapies may involve CRISPR-based gene editing to correct mutations before reintroducing healthy, corrected cells into the patient’s bloodstream.",
            ],
            [
                "title" => "Ethical Considerations and Global Equity",
                "content" => "As these technologies advance, ethical questions arise: Who will have access? How will these innovations affect global blood donation systems? Will synthetic or lab-grown blood be affordable in low-income regions where shortages are most critical? Policymakers, researchers, and medical ethicists must collaborate to ensure these breakthroughs benefit all populations fairly and equitably.",
            ],
            [
                "title" => "The Road Ahead",
                "content" => "While much of this technology is still in development or early clinical trials, the momentum is undeniable. Within the next decade, artificial and lab-grown blood may begin to supplement, or even replace, donor blood in emergency rooms, battlefields, and space missions. The integration of regenerative medicine into mainstream healthcare could also shift our focus from replacement to true healing at the cellular level.",
            ]
        ]
    ]
]

];

// Get selected article
$selectedArticle = null;
if (isset($_GET['article'])) {
    $articleId = $_GET['article'];
    foreach ($articles as $article) {
        if ($article['id'] === $articleId) {
            $selectedArticle = $article;
            break;
        }
    }
}

// Get articles by category
$categories = array_unique(array_column($articles, 'category'));
$articlesByCategory = [];
foreach ($categories as $category) {
    $articlesByCategory[$category] = array_filter($articles, function($article) use ($category) {
        return $article['category'] === $category;
    });
}

// Get featured articles
$featuredArticles = array_filter($articles, function($article) {
    return $article['featured'];
});
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pirate's Blood Bank - Learning Center</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Poppins:wght@300;400;500;600;700;800;900&family=Playfair+Display:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    
<style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --secondary-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --accent-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --success-gradient: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            --warning-gradient: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%);
            --danger-gradient: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%);
            --dark-gradient: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            --card-shadow: 0 20px 60px rgba(0, 0, 0, 0.12);
            --card-hover-shadow: 0 30px 80px rgba(0, 0, 0, 0.18);
            --border-radius: 24px;
            --border-radius-small: 16px;
            --transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
            --text-primary: #2d3748;
            --text-secondary: #4a5568;
            --text-muted: #718096;
            --bg-primary: #ffffff;
            --bg-secondary: #f7fafc;
            --bg-accent: #edf2f7;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            min-height: 100vh;
            line-height: 1.7;
            color: var(--text-primary);
            overflow-x: hidden;
        }

        main {
            margin-left: 70px;
            min-height: 100vh;
            transition: var(--transition);
            padding: 40px;
            width: calc(100vw - 70px);
            overflow-y: auto;
            position: relative;
        }

        main::before {
            content: '';
            position: fixed;
            top: 0;
            left: 70px;
            right: 0;
            bottom: 0;
            background: 
                radial-gradient(ellipse at top left, rgba(102, 126, 234, 0.15) 0%, transparent 50%),
                radial-gradient(ellipse at top right, rgba(118, 75, 162, 0.15) 0%, transparent 50%),
                radial-gradient(ellipse at bottom left, rgba(79, 172, 254, 0.1) 0%, transparent 50%);
            pointer-events: none;
            z-index: 0;
        }
        
        .learn-container {
            padding: 60px;
            width: 100%;
            max-width: 1400px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
            }
        
        .learn-page-header {
            text-align: center;
            margin-bottom: 80px;
            position: relative;
        }

        .learn-page-header::after {
            content: '';
            position: absolute;
            bottom: -40px;
            left: 50%;
            transform: translateX(-50%);
            width: 120px;
            height: 6px;
            background: var(--primary-gradient);
            border-radius: 3px;
        }
        
        .learn-page-title {
            font-size: 56px;
            font-weight: 900;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 25px;
            font-family: 'Playfair Display', serif;
            margin-bottom: 20px;
            letter-spacing: -0.02em;
        }
        
        .learn-page-title i {
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-size: 48px;
        }
        
        .learn-page-subtitle {
            color: var(--text-secondary);
            font-size: 22px;
            font-weight: 500;
            margin-bottom: 40px;
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
            line-height: 1.6;
        }
        
        .quick-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 30px;
            margin-bottom: 50px;
        }
        
        .stat-card {
            background: var(--primary-gradient);
            color: white;
            padding: 35px 25px;
            border-radius: var(--border-radius-small);
            text-align: center;
            position: relative;
            overflow: hidden;
            transition: var(--transition);
            cursor: pointer;
        }

        .stat-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: var(--card-hover-shadow);
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="50" cy="50" r="2" fill="rgba(255,255,255,0.15)"/></svg>') repeat;
            background-size: 20px 20px;
            animation: float 15s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            33% { transform: translateY(-10px) rotate(1deg); }
            66% { transform: translateY(5px) rotate(-1deg); }
        }
        
        .stat-content {
            position: relative;
            z-index: 1;
        }
        
        .stat-number {
            font-size: 36px;
            font-weight: 800;
            margin-bottom: 8px;
            font-family: 'Poppins', sans-serif;
        }
        
        .stat-label {
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            opacity: 0.9;
            font-weight: 600;
        }
        
        .article-view {
            display: none;
        }
        
        .article-view.active {
            display: block;
        }
        
        .article-header {
            background: var(--primary-gradient);
            color: white;
            padding: 60px 50px;
            border-radius: var(--border-radius);
            margin-bottom: 50px;
            position: relative;
            overflow: hidden;
            text-align: center;
        }
        
        .article-header-content {
            position: relative;
            z-index: 1;
            max-width: 900px;
            margin: 0 auto;
        }
        
        .article-category {
            background: rgba(255,255,255,0.25);
            backdrop-filter: blur(10px);
            padding: 12px 24px;
            border-radius: 50px;
            font-size: 14px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            display: inline-block;
            margin-bottom: 25px;
            border: 1px solid rgba(255,255,255,0.3);
        }
        
        .article-title {
            font-size: 48px;
            font-weight: 800;
            margin-bottom: 25px;
            line-height: 1.2;
            font-family: 'Playfair Display', serif;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
            color: white;
        }
        
        .article-meta {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 30px;
            opacity: 0.9;
            flex-wrap: wrap;
            font-size: 16px;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 500;
        }

        .meta-item i {
            font-size: 18px;
        }
        
        .article-content {
            background: var(--bg-primary);
            padding: 60px 50px;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            margin-bottom: 40px;
            position: relative;
        }
        
        .article-intro {
            font-size: 22px;
            line-height: 1.8;
            color: var(--text-secondary);
            margin-bottom: 50px;
            padding: 40px;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.08), rgba(118, 75, 162, 0.08));
            border-radius: var(--border-radius-small);
            border-left: 6px solid;
            border-image: var(--primary-gradient) 1;
            font-weight: 500;
            position: relative;
        }

        .article-intro::before {
            content: '"';
            position: absolute;
            top: 10px;
            left: 20px;
            font-size: 60px;
            font-weight: 900;
            color: rgba(102, 126, 234, 0.3);
            font-family: 'Playfair Display', serif;
        }
        
        .article-section {
            margin-bottom: 60px;
            position: relative;
        }
        
        .learn-section-title {
            font-size: 32px;
            font-weight: 800;
            color: var(--text-primary);
            margin-bottom: 30px;
            display: flex;
            align-items: flex-start;
            gap: 20px;
            font-family: 'Playfair Display', serif;
            line-height: 1.3;
        }
        
        .learn-section-title::before {
            content: '';
            width: 6px;
            min-height: 40px;
            background: var(--primary-gradient);
            border-radius: 3px;
            margin-top: 8px;
            flex-shrink: 0;
        }
        
        .section-content {
            font-size: 18px;
            line-height: 1.9;
            color: var(--text-secondary);
            margin-bottom: 30px;
            text-align: justify;
            font-weight: 400;
        }

        .section-content p {
            margin-bottom: 20px;
        }
        
        .section-image {
            width: 100%;
            max-width: 800px;
            height: 400px;
            object-fit: cover;
            border-radius: var(--border-radius-small);
            margin: 30px auto;
            box-shadow: var(--card-shadow);
            transition: var(--transition);
            display: block;
        }

        .section-image:hover {
            transform: scale(1.02);
            box-shadow: var(--card-hover-shadow);
        }
        
        .blood-type-facts, .facts-section {
            background: linear-gradient(135deg, rgba(255, 243, 224, 0.8), rgba(255, 224, 178, 0.8));
            backdrop-filter: blur(10px);
            padding: 40px;
            border-radius: var(--border-radius-small);
            margin: 40px 0;
            border-left: 6px solid #ff9800;
            position: relative;
            overflow: hidden;
        }

        .blood-type-facts::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 152, 0, 0.1) 0%, transparent 70%);
            animation: rotate 20s linear infinite;
        }

        @keyframes rotate {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .facts-title {
            font-size: 24px;
            font-weight: 800;
            color: #e65100;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 15px;
            font-family: 'Poppins', sans-serif;
            position: relative;
            z-index: 1;
        }

        .facts-title i {
            font-size: 28px;
            animation: pulse 2s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }
        
        .facts-list {
            list-style: none;
            padding: 0;
            margin: 0;
            position: relative;
            z-index: 1;
        }
        
        .facts-list li {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            margin-bottom: 18px;
            color: #bf360c;
            font-weight: 600;
            padding: 15px;
            background: rgba(255, 255, 255, 0.7);
            border-radius: 12px;
            transition: var(--transition);
            cursor: pointer;
            font-size: 16px;
            line-height: 1.6;
        }

        .facts-list li:hover {
            background: rgba(255, 255, 255, 0.9);
            transform: translateX(10px);
            box-shadow: 0 8px 25px rgba(191, 54, 12, 0.15);
        }
        
        .facts-list li::before {
            content: '🩸';
            flex-shrink: 0;
            margin-top: 2px;
            font-size: 20px;
            animation: heartbeat 2s ease-in-out infinite;
        }

        @keyframes heartbeat {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.2); }
        }
        
        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            padding: 16px 32px;
            background: var(--dark-gradient);
            color: white;
            text-decoration: none;
            border-radius: 50px;
            font-weight: 700;
            transition: var(--transition);
            margin-bottom: 40px;
            font-size: 16px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .back-button:hover {
            transform: translateY(-3px);
            box-shadow: var(--card-hover-shadow);
            color: white;
            text-decoration: none;
        }

        .back-button i {
            transition: var(--transition);
        }

        .back-button:hover i {
            transform: translateX(-3px);
        }
        
        .articles-grid {
            display: none;
        }
        
        .articles-grid.active {
            display: block;
        }
        
        .featured-section {
            margin-bottom: 80px;
        }
        
        .learn-section-header {
            font-size: 36px;
            font-weight: 800;
            color: var(--text-primary);
            margin-bottom: 40px;
            display: flex;
            align-items: center;
            gap: 20px;
            font-family: 'Playfair Display', serif;
        }
        
        .learn-section-header i {
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-size: 32px;
        }
        
        .featured-articles {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 40px;
            margin-bottom: 60px;
        }
        
        .article-card {
            background: var(--bg-primary);
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--card-shadow);
            transition: var(--transition);
            cursor: pointer;
            position: relative;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .article-card:hover {
            transform: translateY(-12px) scale(1.02);
            box-shadow: var(--card-hover-shadow);
        }

        .article-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: var(--primary-gradient);
            opacity: 0;
            transition: var(--transition);
            z-index: 1;
        }

        .article-card:hover::before {
            opacity: 0.05;
        }
        
        .card-image-container {
            position: relative;
            overflow: hidden;
            height: 250px;
        }

        .card-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: var(--transition);
        }

        .article-card:hover .card-image {
            transform: scale(1.1);
        }
        
        .card-badge {
            position: absolute;
            top: 20px;
            left: 20px;
            background: var(--primary-gradient);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
            z-index: 2;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
        }

        .card-read-time {
            position: absolute;
            top: 20px;
            right: 20px;
            background: rgba(0,0,0,0.7);
            color: white;
            padding: 8px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            z-index: 2;
            backdrop-filter: blur(10px);
        }
        
        .card-content {
            padding: 35px;
            position: relative;
            z-index: 2;
        }
        
        .card-title {
            font-size: 24px;
            font-weight: 800;
            color: var(--text-primary);
            margin-bottom: 15px;
            line-height: 1.4;
            font-family: 'Playfair Display', serif;
        }
        
        .card-excerpt {
            color: var(--text-secondary);
            font-size: 16px;
            line-height: 1.7;
            margin-bottom: 20px;
            font-weight: 400;
        }
        
        .card-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 14px;
            color: var(--text-muted);
            border-top: 1px solid var(--bg-accent);
            padding-top: 20px;
        }
        
        .card-author {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
        }

        .card-author i {
            color: var(--primary-gradient);
        }

        .card-tags {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 15px;
        }

        .card-tag {
            background: var(--bg-accent);
            color: var(--text-secondary);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: lowercase;
        }
        
        .category-section {
            margin-bottom: 60px;
        }
        
        .category-title {
            font-size: 32px;
            font-weight: 800;
            color: var(--text-primary);
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 4px solid;
            border-image: var(--primary-gradient) 1;
            display: inline-block;
            font-family: 'Playfair Display', serif;
        }
        
        .category-articles {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 30px;
        }
        
        .small-article-card {
            background: var(--bg-primary);
            border-radius: var(--border-radius-small);
            padding: 30px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
            transition: var(--transition);
            cursor: pointer;
            border-left: 5px solid;
            border-image: var(--primary-gradient) 1;
            position: relative;
            overflow: hidden;
        }

        .small-article-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: var(--primary-gradient);
            opacity: 0;
            transition: var(--transition);
        }
        
        .small-article-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--card-hover-shadow);
        }

        .small-article-card:hover::before {
            opacity: 0.03;
        }
        
        .small-card-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 12px;
            font-family: 'Poppins', sans-serif;
            position: relative;
            z-index: 1;
        }
        
        .small-card-excerpt {
            color: var(--text-secondary);
            font-size: 15px;
            line-height: 1.6;
            margin-bottom: 15px;
            position: relative;
            z-index: 1;
        }
        
        .small-card-meta {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            color: var(--text-muted);
            font-weight: 500;
            position: relative;
            z-index: 1;
        }

        .search-container {
            text-align: center;
            margin-bottom: 50px;
        }

        .search-input {
            width: 100%;
            max-width: 500px;
            padding: 18px 25px;
            border: 2px solid var(--bg-accent);
            border-radius: 50px;
            font-size: 18px;
            font-weight: 500;
            transition: var(--transition);
            background: var(--bg-primary);
            color: var(--text-primary);
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
        }
        
        .search-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1), 0 8px 25px rgba(0,0,0,0.15);
            transform: scale(1.02);
        }

        .search-input::placeholder {
            color: var(--text-muted);
        }

        .table-of-contents {
            background: var(--bg-secondary);
            padding: 30px;
            border-radius: var(--border-radius-small);
            margin: 40px 0;
            border-left: 5px solid;
            border-image: var(--primary-gradient) 1;
        }

        .toc-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 20px;
            font-family: 'Poppins', sans-serif;
        }

        .toc-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .toc-item {
            margin-bottom: 10px;
        }

        .toc-link {
            color: var(--text-secondary);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
            display: block;
            padding: 8px 0;
            border-bottom: 1px solid transparent;
        }

        .toc-link:hover {
            color: #667eea;
            text-decoration: none;
            border-bottom-color: #667eea;
            transform: translateX(5px);
        }

        .cta-section {
            margin-top: 80px;
            padding: 60px 50px;
            background: var(--primary-gradient);
            border-radius: var(--border-radius);
            color: white;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .cta-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="50" cy="50" r="2" fill="rgba(255,255,255,0.1)"/></svg>') repeat;
            background-size: 25px 25px;
            animation: float 20s ease-in-out infinite;
        }

        .cta-content {
            position: relative;
            z-index: 1;
            max-width: 800px;
            margin: 0 auto;
        }

        .cta-title {
            font-size: 36px;
            font-weight: 800;
            margin-bottom: 20px;
            font-family: 'Playfair Display', serif;
        }

        .cta-subtitle {
            font-size: 20px;
            margin-bottom: 40px;
            opacity: 0.9;
            line-height: 1.6;
        }

        .cta-buttons {
            display: flex;
            gap: 25px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .cta-button {
            padding: 18px 35px;
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            color: white;
            text-decoration: none;
            border-radius: 50px;
            font-weight: 700;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: var(--transition);
            border: 2px solid rgba(255, 255, 255, 0.3);
        }

        .cta-button:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
            color: white;
            text-decoration: none;
        }

        .cta-button i {
            font-size: 18px;
        }

        .educational-tips {
            margin-top: 60px;
            padding: 50px;
            background: linear-gradient(135deg, rgba(255, 248, 225, 0.8), rgba(255, 243, 196, 0.8));
            backdrop-filter: blur(10px);
            border-radius: var(--border-radius);
            border-left: 6px solid #ffc107;
            position: relative;
            margin-bottom: 10px;
        }

        .educational-tips::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 193, 7, 0.1) 0%, transparent 70%);
            animation: rotate 25s linear infinite;
        }

        .tips-title {
            color: #f57c00;
            font-size: 30px;
            font-weight: 800;
            text-align: center;
            margin-bottom: 40px;
            font-family: 'Playfair Display', serif;
            position: relative;
            z-index: 1;
        }

        .tips-title i {
            font-size: 32px;
            margin-right: 15px;
            animation: pulse 2s ease-in-out infinite;
        }

        .tips-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            text-align: center;
            position: relative;
            z-index: 1;
        }

        .tip-card {
            padding: 30px 25px;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border-radius: var(--border-radius-small);
            transition: var(--transition);
            cursor: pointer;
            border: 1px solid rgba(255, 193, 7, 0.2);
        }

        .tip-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(245, 124, 0, 0.15);
            background: rgba(255, 255, 255, 0.95);
        }

        .tip-card i {
            font-size: 40px;
            margin-bottom: 15px;
            display: block;
        }

        .tip-card .fa-tint { color: #dc3545; }
        .tip-card .fa-users { color: #28a745; }
        .tip-card .fa-heart { color: #6f42c1; }
        .tip-card .fa-clock { color: #17a2b8; }

        .tip-title {
            color: #f57c00;
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 8px;
            font-family: 'Poppins', sans-serif;
        }

        .tip-text {
            color: #ff8f00;
            font-size: 14px;
            font-weight: 500;
            line-height: 1.5;
        }

        .progress-bar {
            position: fixed;
            top: 0;
            left: 0;
            width: 0%;
            height: 4px;
            background: var(--primary-gradient);
            z-index: 10000;
            transition: width 0.3s ease;
        }

        .reading-time {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: var(--primary-gradient);
            color: white;
            padding: 12px 20px;
            border-radius: 50px;
            font-size: 14px;
            font-weight: 600;
            box-shadow: var(--card-shadow);
            z-index: 1000;
            display: none;
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .learn-container {
                padding: 40px;
            }
            
            .featured-articles {
                grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            }
        }

        @media (max-width: 768px) {
            main {
                margin-left: 0;
                padding: 20px;
                width: 100vw;
                margin-top: 100px; 
                margin-bottom: 80px;/* Adjust for fixed header */
            }
            
            main::before {
                left: 0;
            }
            
            .learn-container {
                padding: 30px 25px;
                border-radius: var(--border-radius-small);
            }
            
            .learn-page-title {
                font-size: 36px;
                flex-direction: column;
                gap: 15px;
            }

            .learn-page-title i {
                font-size: 32px;
            }

            .learn-page-subtitle {
                font-size: 18px;
            }
            
            .article-title {
                font-size: 32px;
            }
            
            .article-header {
                padding: 40px 30px;
            }
            
            .article-content {
                padding: 40px 30px;
            }

            .learn-section-title {
                font-size: 24px;
            }

            .section-content {
                font-size: 16px;
            }
            
            .featured-articles,
            .category-articles {
                grid-template-columns: 1fr;
            }
            
            .quick-stats {
                grid-template-columns: repeat(2, 1fr);
            }

            .tips-grid {
                grid-template-columns: 1fr;
            }

            .cta-buttons {
                flex-direction: column;
                align-items: center;
            }

            .cta-button {
                width: 100%;
                max-width: 300px;
                justify-content: center;
            }
        }
        
        @media (max-width: 480px) {

            .learn-page-title {
                font-size: 28px;
            }
            
            .article-meta {
                flex-direction: column;
                align-items: center;
                gap: 15px;
            }
            
            .quick-stats {
                grid-template-columns: 1fr;
            }

            .search-input {
                font-size: 16px;
                padding: 15px 20px;
            }

            .section-image {
                height: 250px;
            }
        }
    </style>
</head>

<body>
<?php 
if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin') {
    include('includes/adminheader.php');
} else {
    include('includes/header.php');
}
?>
<?php include('includes/sidebar.php'); ?>

<main>
    <div class="progress-bar" id="progressBar"></div>
    <div class="learn-container">
        <!-- Article View -->
        <?php if ($selectedArticle): ?>
            <div class="article-view active">
                <a href="learn.php" class="back-button">
                    <i class="fas fa-arrow-left"></i>
                    Back to Learning Center
                </a>
                
                <div class="article-header">
                    <div class="article-header-content">
                        <div class="article-category"><?php echo htmlspecialchars($selectedArticle['category']); ?></div>
                        <h1 class="article-title"><?php echo htmlspecialchars($selectedArticle['title']); ?></h1>
                        <div class="article-meta">
                            <div class="meta-item">
                                <i class="fas fa-clock"></i>
                                <?php echo htmlspecialchars($selectedArticle['read_time']); ?>
                            </div>
                            <div class="meta-item">
                                <i class="fas fa-user"></i>
                                <?php echo htmlspecialchars($selectedArticle['author']); ?>
                            </div>
                            <div class="meta-item">
                                <i class="fas fa-calendar"></i>
                                <?php echo date('M j, Y', strtotime($selectedArticle['date'])); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Table of Contents -->
                <div class="table-of-contents">
                    <h3 class="toc-title">
                        <i class="fas fa-list"></i>
                        Table of Contents
                    </h3>
                    <ul class="toc-list">
                        <?php foreach ($selectedArticle['content']['sections'] as $index => $section): ?>
                        <div class="article-section" id="section-<?php echo $index; ?>">
                            <h2 class="section-title"><?php echo htmlspecialchars($section['title']); ?></h2>
                            <div class="section-content">
                                <?php echo nl2br(htmlspecialchars($section['content'])); ?>
                            </div>
                            
                            <?php if (isset($section['image'])): ?>
                                <img src="<?php echo $section['image']; ?>" 
                                     alt="<?php echo htmlspecialchars($section['title']); ?>" 
                                     class="section-image"
                                     loading="lazy">
                            <?php endif; ?>
                            
                            <?php if (isset($section['facts'])): ?>
                                <div class="blood-type-facts">
                                    <div class="facts-title">
                                        <i class="fas fa-lightbulb"></i>
                                        Key Facts & Insights
                                    </div>
                                    <ul class="facts-list">
                                        <?php foreach ($section['facts'] as $fact): ?>
                                            <li><?php echo htmlspecialchars($fact); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>

                    <!-- Article Tags -->
                    <?php if (isset($selectedArticle['tags'])): ?>
                    <div class="card-tags" style="margin-top: 40px; padding-top: 30px; border-top: 2px solid var(--bg-accent);">
                        <strong style="margin-right: 15px; color: var(--text-primary);">Tags:</strong>
                        <?php foreach ($selectedArticle['tags'] as $tag): ?>
                            <span class="card-tag">#<?php echo htmlspecialchars($tag); ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="reading-time" id="readingTime">
                    <i class="fas fa-book-reader"></i>
                    <span id="timeRemaining"></span> min left
                </div>
            </div>
        <?php else: ?>
            <!-- Articles Grid View -->
            <div class="articles-grid active">
                <div class="learn-header">
                    <h1 class="learn-title">
                        <i class="fas fa-graduation-cap"></i>
                        Blood Donation Learning Center
                    </h1>
                    <p class="learn-subtitle">Discover comprehensive, evidence-based information about blood donation, blood types, medical innovations, and the global impact of this life-saving practice</p>
                    <br><br>
                    <!-- Quick Stats -->
                    <div class="quick-stats">
                        <div class="stat-card">
                            <div class="stat-content">
                                <div class="stat-number"><?php echo count($articles); ?></div>
                                <div class="stat-label">Expert Articles</div>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-content">
                                <div class="stat-number"><?php echo count($categories); ?></div>
                                <div class="stat-label">Categories</div>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-content">
                                <div class="stat-number">15+</div>
                                <div class="stat-label">Min Average</div>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-content">
                                <div class="stat-number">100%</div>
                                <div class="stat-label">Free Access</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if ($message): ?>
                    <div class="message error" style="background: var(--danger-gradient); color: white; padding: 20px; border-radius: var(--border-radius-small); margin-bottom: 30px;">
                        <i class="fas fa-exclamation-triangle"></i>
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <!-- Search Section -->
                <div class="search-container">
                    <input type="text" id="searchInput" class="search-input" placeholder="Search articles by title, category, or content...">
                </div>
                
                <!-- Featured Articles -->
                <div class="featured-section">
                    <h2 class="section-header">
                        <i class="fas fa-star"></i>
                        Featured Articles
                    </h2>
                    
                    <div class="featured-articles">
                        <?php foreach ($featuredArticles as $article): ?>
                            <div class="article-card" onclick="viewArticle('<?php echo $article['id']; ?>')">
                                <div class="card-image-container">
                                    <img src="<?php echo $article['image']; ?>" 
                                         alt="<?php echo htmlspecialchars($article['title']); ?>" 
                                         class="card-image"
                                         loading="lazy">
                                    <div class="card-badge"><?php echo htmlspecialchars($article['category']); ?></div>
                                    <div class="card-read-time">
                                        <i class="fas fa-clock"></i>
                                        <?php echo htmlspecialchars($article['read_time']); ?>
                                    </div>
                                </div>
                                
                                <div class="card-content">
                                    <h3 class="card-title"><?php echo htmlspecialchars($article['title']); ?></h3>
                                    <p class="card-excerpt"><?php echo htmlspecialchars($article['excerpt']); ?></p>
                                    
                                    <?php if (isset($article['tags'])): ?>
                                    <div class="card-tags">
                                        <?php foreach (array_slice($article['tags'], 0, 3) as $tag): ?>
                                            <span class="card-tag">#<?php echo htmlspecialchars($tag); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="card-meta">
                                        <div class="card-author">
                                            <i class="fas fa-user"></i>
                                            <?php echo htmlspecialchars($article['author']); ?>
                                        </div>
                                        <div class="card-date">
                                            <i class="fas fa-calendar"></i>
                                            <?php echo date('M j, Y', strtotime($article['date'])); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Articles by Category -->
                <?php foreach ($articlesByCategory as $category => $categoryArticles): ?>
                    <div class="category-section">
                        <h2 class="category-title"><?php echo htmlspecialchars($category); ?></h2>
                        
                        <div class="category-articles">
                            <?php foreach ($categoryArticles as $article): ?>
                                <div class="small-article-card" onclick="viewArticle('<?php echo $article['id']; ?>')">
                                    <h4 class="small-card-title"><?php echo htmlspecialchars($article['title']); ?></h4>
                                    <p class="small-card-excerpt"><?php echo htmlspecialchars($article['excerpt']); ?></p>
                                    <div class="small-card-meta">
                                        <span>
                                            <i class="fas fa-user"></i> 
                                            <?php echo htmlspecialchars($article['author']); ?>
                                        </span>
                                        <span>
                                            <i class="fas fa-clock"></i> 
                                            <?php echo htmlspecialchars($article['read_time']); ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <!-- Call to Action -->
                <div class="cta-section">
                    <div class="cta-content">
                        <h3 class="cta-title">
                            <i class="fas fa-heart" style="color: #ff6b6b; margin-right: 15px;"></i>
                            Ready to Make a Life-Saving Impact?
                        </h3>
                        <p class="cta-subtitle">
                            Transform your knowledge into action. Join thousands of heroes who are saving lives through blood donation and helping build stronger, healthier communities worldwide.
                        </p>
                        <div class="cta-buttons">
                            <a href="donate.php" class="cta-button">
                                <i class="fas fa-heart"></i>
                                Start Donating Blood
                            </a>
                            <a href="receive.php" class="cta-button">
                                <i class="fas fa-plus-circle"></i>
                                Request Blood Help
                            </a>
                            <a href="locations.php" class="cta-button">
                                <i class="fas fa-map-marker-alt"></i>
                                Find Nearby Centers
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Educational Tips -->
                <div class="educational-tips">
                    <h3 class="tips-title">
                        <i class="fas fa-lightbulb"></i>
                        Essential Blood Donation Facts
                    </h3>
                    
                    <div class="tips-grid">
                        <div class="tip-card">
                            <i class="fas fa-tint"></i>
                            <h4 class="tip-title">Every 2 Seconds</h4>
                            <p class="tip-text">Someone in the Philippines needs life-saving blood transfusion</p>
                        </div>
                        
                        <div class="tip-card">
                            <i class="fas fa-users"></i>
                            <h4 class="tip-title">1 in 7 Patients</h4>
                            <p class="tip-text">Entering hospitals require immediate blood transfusion</p>
                        </div>
                        
                        <div class="tip-card">
                            <i class="fas fa-heart"></i>
                            <h4 class="tip-title">3 Lives Saved</h4>
                            <p class="tip-text">With every single blood donation made by generous donors</p>
                        </div>
                        
                        <div class="tip-card">
                            <i class="fas fa-clock"></i>
                            <h4 class="tip-title">10 Minutes</h4>
                            <p class="tip-text">That's all the time needed to donate blood and save lives</p>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</main>

<script>
    // Article viewing functionality
    function viewArticle(articleId) {
        window.location.href = 'learn.php?article=' + encodeURIComponent(articleId);
    }

    // Search functionality
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                const articleCards = document.querySelectorAll('.article-card, .small-article-card');
                
                articleCards.forEach(card => {
                    const title = card.querySelector('.card-title, .small-card-title').textContent.toLowerCase();
                    const excerpt = card.querySelector('.card-excerpt, .small-card-excerpt').textContent.toLowerCase();
                    const category = card.querySelector('.card-badge') ? 
                        card.querySelector('.card-badge').textContent.toLowerCase() : '';
                    
                    if (title.includes(searchTerm) || excerpt.includes(searchTerm) || category.includes(searchTerm)) {
                        card.style.display = 'block';
                        card.closest('.category-section, .featured-section').style.display = 'block';
                    } else {
                        card.style.display = 'none';
                    }
                });

                // Hide empty sections
                document.querySelectorAll('.category-section, .featured-section').forEach(section => {
                    const visibleCards = section.querySelectorAll('.article-card:not([style*="display: none"]), .small-article-card:not([style*="display: none"])');
                    if (visibleCards.length === 0 && searchTerm !== '') {
                        section.style.display = 'none';
                    } else {
                        section.style.display = 'block';
                    }
                });
            });
        }

        // Reading progress and time estimation
        const progressBar = document.getElementById('progressBar');
        const readingTime = document.getElementById('readingTime');
        const timeRemaining = document.getElementById('timeRemaining');
        
        if (progressBar && document.querySelector('.article-content')) {
            const articleContent = document.querySelector('.article-content');
            const wordsPerMinute = 200; // Average reading speed
            const totalWords = articleContent.textContent.split(/\s+/).length;
            const estimatedTime = Math.ceil(totalWords / wordsPerMinute);
            
            let startTime = Date.now();
            let timeSpent = 0;
            
            if (readingTime && timeRemaining) {
                readingTime.style.display = 'block';
                timeRemaining.textContent = estimatedTime;
            }

            // Update progress based on scroll
            window.addEventListener('scroll', function() {
                const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
                const scrollHeight = document.documentElement.scrollHeight - window.innerHeight;
                const scrollPercent = (scrollTop / scrollHeight) * 100;
                
                progressBar.style.width = Math.min(scrollPercent, 100) + '%';
                
                // Update reading time
                timeSpent = Math.floor((Date.now() - startTime) / 60000); // Convert to minutes
                const remainingTime = Math.max(estimatedTime - timeSpent, 0);
                if (timeRemaining) {
                    timeRemaining.textContent = remainingTime;
                }
            });
        }

        // Smooth scrolling for table of contents
        document.querySelectorAll('.toc-link').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const targetId = this.getAttribute('href').substring(1);
                const targetElement = document.getElementById(targetId);
                
                if (targetElement) {
                    targetElement.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Animate stats on scroll
        const observerOptions = {
            threshold: 0.5,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const statNumber = entry.target.querySelector('.stat-number');
                    const finalNumber = parseInt(statNumber.textContent);
                    const suffix = statNumber.textContent.replace(/[0-9]/g, '');
                    
                    animateNumber(statNumber, 0, finalNumber, 2000, suffix);
                    observer.unobserve(entry.target);
                }
            });
        }, observerOptions);

        document.querySelectorAll('.stat-card').forEach(card => {
            observer.observe(card);
        });

        // Number animation function
        function animateNumber(element, start, end, duration, suffix = '') {
            const range = end - start;
            const startTime = performance.now();
            
            function updateNumber(currentTime) {
                const elapsed = currentTime - startTime;
                const progress = Math.min(elapsed / duration, 1);
                
                // Easing function for smooth animation
                const easeOutCubic = 1 - Math.pow(1 - progress, 3);
                const current = Math.floor(start + (range * easeOutCubic));
                
                element.textContent = current + suffix;
                
                if (progress < 1) {
                    requestAnimationFrame(updateNumber);
                }
            }
            
            requestAnimationFrame(updateNumber);
        }

        // Add loading states
        document.querySelectorAll('.article-card, .small-article-card').forEach(card => {
            card.addEventListener('click', function() {
                const overlay = document.createElement('div');
                overlay.style.cssText = `
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(102, 126, 234, 0.9);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    z-index: 10000;
                    color: white;
                    font-size: 24px;
                    font-weight: 600;
                `;
                overlay.innerHTML = `
                    <div style="text-align: center;">
                        <i class="fas fa-spinner fa-spin" style="font-size: 48px; margin-bottom: 20px; display: block;"></i>
                        Loading article...
                    </div>
                `;
                document.body.appendChild(overlay);
            });
        });

        // Keyboard navigation
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && document.querySelector('.article-view.active')) {
                window.location.href = 'learn.php';
            }
            
            if (e.key === '/' && !e.ctrlKey && !e.metaKey) {
                e.preventDefault();
                const searchInput = document.getElementById('searchInput');
                if (searchInput) {
                    searchInput.focus();
                }
            }
        });

        // Add tooltips to interactive elements
        document.querySelectorAll('[title]').forEach(element => {
            element.addEventListener('mouseenter', function() {
                const tooltip = document.createElement('div');
                tooltip.className = 'tooltip';
                tooltip.textContent = this.getAttribute('title');
                tooltip.style.cssText = `
                    position: absolute;
                    background: rgba(0, 0, 0, 0.8);
                    color: white;
                    padding: 8px 12px;
                    border-radius: 6px;
                    font-size: 14px;
                    z-index: 1000;
                    pointer-events: none;
                    white-space: nowrap;
                `;
                document.body.appendChild(tooltip);
                
                const rect = this.getBoundingClientRect();
                tooltip.style.left = (rect.left + rect.width / 2 - tooltip.offsetWidth / 2) + 'px';
                tooltip.style.top = (rect.top - tooltip.offsetHeight - 10) + 'px';
                
                this.addEventListener('mouseleave', function() {
                    if (tooltip.parentNode) {
                        tooltip.parentNode.removeChild(tooltip);
                    }
                }, { once: true });
            });
        });
    });

    // Print functionality
    function printArticle() {
        window.print();
    }

    // Share functionality
    async function shareArticle() {
        if (navigator.share) {
            try {
                await navigator.share({
                    title: document.title,
                    text: 'Check out this educational article about blood donation',
                    url: window.location.href
                });
            } catch (err) {
                console.log('Error sharing:', err);
            }
        } else {
            // Fallback to copying URL to clipboard
            navigator.clipboard.writeText(window.location.href).then(() => {
                alert('Article URL copied to clipboard!');
            });
        }
    }

    // Add print and share buttons if viewing an article
    if (document.querySelector('.article-view.active')) {
        const backButton = document.querySelector('.back-button');
        if (backButton) {
            const actionButtons = document.createElement('div');
            actionButtons.style.cssText = 'display: flex; gap: 15px; margin-bottom: 20px;';
            actionButtons.innerHTML = `
                <button onclick="printArticle()" class="back-button" style="margin-bottom: 0;">
                    <i class="fas fa-print"></i>
                    Print Article
                </button>
                <button onclick="shareArticle()" class="back-button" style="margin-bottom: 0;">
                    <i class="fas fa-share"></i>
                    Share Article
                </button>
            `;
            backButton.parentNode.insertBefore(actionButtons, backButton.nextSibling);
        }
    }
</script>
</body>
<?php include('includes/footer.php'); ?>

</html>