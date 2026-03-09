#!/usr/bin/env python3
"""
Script pour générer un logo IA pour Carnet de Jeu
Utilise Replicate API (gratuit avec 50 credits)

Installation:
    pip install replicate requests

Usage:
    python scripts/generate_logo.py
    # Génère: docs/images/logo.png
"""

import os
import sys
import requests
from pathlib import Path

def fetch_and_save_image(url: str, output_path: str):
    """Télécharge et sauvegarde une image."""
    response = requests.get(url)
    response.raise_for_status()
    
    with open(output_path, 'wb') as f:
        f.write(response.content)
    print(f"✅ Logo sauvegardé: {output_path}")

def generate_with_stability_api():
    """Génère un logo via l'API Stability AI (alternative à Replicate)"""
    api_key = os.getenv('STABILITY_API_KEY')
    if not api_key:
        print("⚠️  STABILITY_API_KEY non défini. Skipping Stability API.")
        return None
    
    print("🎨 Génération via Stability AI...")
    url = "https://api.stability.ai/v1/generate"
    headers = {
        "Authorization": f"Bearer {api_key}",
        "Accept": "application/json",
    }
    
    prompt = """
    Minimalist gaming logo for "Carnet de Jeu" (French for "Game Log").
    - Clean geometric shapes (dice + quill/pen)
    - Flat design, 2-3 colors max
    - Perfect for favicon (must be scalable)
    - Modern, professional yet playful
    - No text, just symbol
    - Teal/green accent color preferred
    """
    
    try:
        import replicate
        output = replicate.run(
            "stability-ai/stable-diffusion",
            input={
                "prompt": prompt,
                "num_outputs": 1,
                "size": "512x512",
                "scheduler": "K_LMS"
            }
        )
        return output[0] if output else None
    except ImportError:
        print("⚠️  replicate not installed. Run: pip install replicate")
        return None
    except Exception as e:
        print(f"❌ Erreur Replicate: {e}")
        return None

def generate_with_ascii_fallback():
    """Crée un logo SVG minimaliste en fallback"""
    print("🎨 Création logo SVG minimaliste (fallback)...")
    
    svg_content = """<?xml version="1.0" encoding="UTF-8"?>
<svg viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg">
  <!-- Background circle -->
  <circle cx="100" cy="100" r="95" fill="#0f766e" opacity="0.1" stroke="#0f766e" stroke-width="2"/>
  
  <!-- Dice (left) -->
  <g transform="translate(65, 80)">
    <rect x="0" y="0" width="30" height="30" fill="none" stroke="#0f766e" stroke-width="2" rx="3"/>
    <circle cx="8" cy="8" r="2" fill="#0f766e"/>
    <circle cx="15" cy="15" r="2" fill="#0f766e"/>
    <circle cx="22" cy="22" r="2" fill="#0f766e"/>
  </g>
  
  <!-- Quill/Pen (right) -->
  <g transform="translate(105, 75)">
    <path d="M 10 0 Q 12 5 10 10 L 5 25 Q 4 28 2 30" 
          fill="none" stroke="#0f766e" stroke-width="2.5" stroke-linecap="round"/>
    <circle cx="10" cy="0" r="3" fill="#0f766e"/>
  </g>
  
  <!-- Text -->
  <text x="100" y="125" font-family="Arial, sans-serif" font-size="14" 
        font-weight="bold" text-anchor="middle" fill="#0f766e">
    CARNET DE JEU
  </text>
</svg>"""
    
    output_path = Path("docs/images/logo.svg")
    output_path.parent.mkdir(parents=True, exist_ok=True)
    
    with open(output_path, 'w') as f:
        f.write(svg_content)
    
    print(f"✅ Logo SVG créé: {output_path}")
    return str(output_path)

def main():
    print("🎮 Carnet de Jeu - Logo Generation Script")
    print("-" * 50)
    
    # Créer le dossier images
    Path("docs/images").mkdir(parents=True, exist_ok=True)
    
    # Essayer Replicate API d'abord
    print("\n1️⃣  Tentative avec Replicate API...")
    api_key = os.getenv('REPLICATE_API_TOKEN')
    
    if api_key:
        try:
            import replicate
            print("✅ replicate module trouvé")
            
            prompt = """
            Minimalist gaming logo for "Carnet de Jeu" - Clean dice and quill icon.
            Flat design, teal/green (#0f766e), geometric, no background, 512x512, professional.
            """
            
            output = replicate.run(
                "stability-ai/stable-diffusion",
                input={
                    "prompt": prompt,
                    "num_outputs": 1,
                    "size": "512x512",
                }
            )
            
            if output and output[0]:
                fetch_and_save_image(output[0], "docs/images/logo.png")
                return
        except Exception as e:
            print(f"⚠️  Replicate échoué: {e}")
    else:
        print("⚠️  REPLICATE_API_TOKEN non défini")
    
    # Fallback: SVG minimaliste
    print("\n2️⃣  Fallback: création SVG minimaliste...")
    generate_with_ascii_fallback()
    
    print("\n" + "="*50)
    print("💡 PROCHAINES ÉTAPES:")
    print("   1. Pour une meilleure qualité: ")
    print("      export REPLICATE_API_TOKEN=votre_token")
    print("   2. Logo SVG prêt: docs/images/logo.svg")
    print("   3. À intégrer dans templates/base.html.twig")
    print("="*50)

if __name__ == "__main__":
    main()
