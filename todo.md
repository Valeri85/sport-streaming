todo:
future:

1. we can move right side bar inside main as it is not releted the global site. and keep left side bar outside

AI instructions:

1. think step-by-step and explain your reasoning.
2. I am new in development, give me step by step, with good explanations.
3. Do not add any feature in code by yourself, before discussing with me.
4. After your answer on my prompt, sometimes there is an issue with artifact, code is not updated. that is why rewrite code in the file witch you edit every time you answer me to avoid technical problems.
5. Do not write documentation after your answer. no need at the end summery documentation.
6. CMS domain path: /var/www/u1852176/data/www/watchlivesport.online
7. Root path for all streaming websites (for example 20 websites): /var/www/u1852176/data/www/streaming
8. Check before edit any file, not to loose already existing functionality.
9. always write CSS and JS in their appropriate files, and if they do not exist create them.

prompt:

ADD NEW WEBSITE:

1. reg.ru-ზე უნდა შეიქმნას ახალი საიტი, წაიშლოს ძველი. რადგან უკვე არსებულს რუთს ვერ უცვლი, ახალ საიტში რუთად უნდა გაეწეროს streaming

1. I want you to translate, for all websites from page_seo: seo title and description.
1. Act as a professional SEO specialist, translation should be:

- Google-optimized,
- Natural,
- Human-Like Language,
- SEO Optimization,
- Brand Consistency (for example: sportlemon should not be translated, other brands also, FIFA, NHL and so on),
- Action-Oriented,
- Grammar Checked: All translations reviewed for proper grammar and spelling.

3. start translating from p2p4u.us website and than so on. structure should be as in af.json attached file. and in general json file you are working on should have all fields as af.json.
4. we need to add translated versions of "Rugby Sevens" and "Extreme Sport" to the "sports" field if they missed.
5. work on da.json. if site you are working is first, give me full json file with editions like in af.json, but if not first site, give me code only inside "seo" json field. for example: "seo": {"domain.com": {...}}.
6. give me code one by one (one site per prompt), work on one website and that after my confirmation continue to another.

give me from "domain.com": {...} field.

First - Fix the missing fields in the base file (direction, missing sports)
Then - Add SEO translations for all 6 websites one by one
