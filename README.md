# YandevPoster (AI Social Poster)

A custom WordPress plugin that automatically summarizes your published articles using OpenAI (GPT-4o) and pushes the content to a webhook (n8n, Make, Zapier) for multi-platform social media distribution.

## Features

- **Automated Summarization**: Uses OpenAI API to generate engaging 2-3 sentence summaries optimized for social media.
- **Webhook Integration**: Sends the summary, images, and post link to any automation platform (n8n, Make, Zapier, etc.) via a simple JSON webhook.
- **Smart Image Extraction**: Automatically finds the Featured Image and inline images from your post to include in the payload.
- **Selective Posting**: Choose which platforms (Facebook, LinkedIn, Instagram, X) to target via a simple sidebar meta box in the post editor.
- **Duplicate Prevention**: Logic to prevent re-posting updates or edits unnecessarily.

## Installation

1.  Download or clone this repository into your WordPress plugins directory:
    ```bash
    cd wp-content/plugins
    git clone https://github.com/ChadiBenhaddou/YandevPoster.git
    ```
2.  Log in to your WordPress Admin dashboard.
3.  Go to **Plugins** and activate **AI Social Poster**.

## Configuration

1.  Navigate to **Settings > AI Social Poster**.
2.  **OpenAI API Key**: Enter your OpenAI API Key (needs access to `gpt-4o`).
3.  **Social Media Webhook URL**: Enter the URL of your automation webhook (see below).

## Automation Setup (n8n / Make / Zapier)

Create a workflow in your automation tool that triggers on a **POST Webhook**. The plugin sends the following JSON payload:

```json
{
  "summary": "This is an AI-generated summary of the article...",
  "platforms": [
    "facebook",
    "linkedin",
    "twitter",
    "instagram"
  ],
  "images": [
    "https://example.com/wp-content/uploads/featured-image.jpg",
    "https://example.com/wp-content/uploads/inline-image.png"
  ],
  "post_title": "My Awesome Article Title",
  "post_url": "https://example.com/my-awesome-article/",
  "post_id": 123
}
```

### Example Workflow Logic:
1.  **Trigger**: Webhook (POST).
2.  **Condition (Switch)**: Check `platforms` array.
    -   If contains `facebook` -> Post to Facebook Page.
    -   If contains `linkedin` -> Create Company Update.
    -   If contains `twitter` -> Post Tweet.
3.  **Action**: Use the `summary` as the post text and attach images from the `images` array.

## Usage

1.  Create a new Post in WordPress.
2.  In the right sidebar, look for the **Social Media Auto-Post** box.
3.  Select the platforms you want to publish to (defaults to all).
4.  **Publish** your post.
5.  The plugin will automatically summarize the content and send the data to your webhook.
