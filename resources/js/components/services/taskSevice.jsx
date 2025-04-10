import axios from 'axios';

const TaskService = {
    unassignTask: async (completed) => {
        const data = JSON.stringify({
            "completed": completed || "0",
        });

        const config = {
            method: 'post',
            maxBodyLength: Infinity,
            url: 'http://backend.itask.intelligrp.com/unassign-task',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': 'TOPEkhHmw5zZgpuhmhVahdizlWg7UqqNjSpXJsS1',
                'Cookie': 'your-cookie-name=your-cookie-value; XSRF-TOKEN=eyJpdiI6Im9UNGt3eXMrQUhHRFdnaTEzSEliVUE9PSIsInZhbHVlIjoiR2JzYkFIVkxyeTBpbHVhVjc2WHVJb1ZIak1HSlFNUGxhZnA4dnF4dVpDRlJjcUt3Qzl0VW1kT2J5anpBOGk2UjdVcWNJQjhGODliU005bVJ1T0VzYzJjampVZjlFMTBXRzRQZ1VGem9oUUV5Q2c2VVBWUUxuVXZCQjFUU2w4cEUiLCJtYWMiOiI5OTAyMGU0MDRmZjhjN2NiNjg4ZGFkNWMzYjBkMzZkMjZiNzcwOTc5ODhhYTY4YmVjZTEzYTkzYWNkZDNlZThlIiwidGFnIjoiIn0%3D; itask_session=eyJpdiI6IlBqVVJ3WkJJbE13ZWNWdTZ6a2RRVWc9PSIsInZhbHVlIjoiUkdBbmtXZ3lQRjk2eUZMaXRTeU1UUURYZ2xzRVA1NDBSRHdIZVhWOUdiNjljQnp4NUVjMzhTVTdkSGxlYXdyelNBWHZnTFlJV0MwSmRZOXZwUUlHWnVyN3RIbHlWOHUxUnp1eGNYUUNDcDhoS1N3cVNDVVFWZWM1dFYxbkZFN2QiLCJtYWMiOiIyNWRmNTYxMWYzNjYwNTdkN2U4MjcxMGNiMWU0OGFkYzY0NGY1ZGU5MDZmZmRmNmFlZjI0NmJkNWI1NTkzMzY3IiwidGFnIjoiIn0%3D',
            },
            data: data,
        };

        try {
            const response = await axios.request(config);
            return response.data;
        } catch (error) {
            console.error("Error in unassignTask:", error);
            throw error;
        }
    },
   
};

export default TaskService;